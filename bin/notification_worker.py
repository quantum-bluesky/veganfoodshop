#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Worker Xử Lý Hàng Đợi Thông Báo SQLite (Notification Outbox Worker)
Ngôn ngữ: Python 3 (Thư viện chuẩn, zero pip external packages)
VeganFoodShop (Bếp Chay An Lành)

Cách dùng:
  python3 bin/notification_worker.py --run-once
  python3 bin/notification_worker.py --daemon --sleep 5
"""

import sys
import os
import time
import json
import sqlite3
import smtplib
import ssl
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from datetime import datetime, timedelta
import argparse

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(SCRIPT_DIR)
DB_PATH = os.path.join(PROJECT_ROOT, "database", "bep_an_yen.db")
LOG_PATH = os.path.join(PROJECT_ROOT, "logs", "worker_py.log")

def log(msg: str):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{timestamp}] [Python Worker] {msg}"
    print(line)
    try:
        os.makedirs(os.path.dirname(LOG_PATH), exist_ok=True)
        with open(LOG_PATH, "a", encoding="utf-8") as f:
            f.write(line + "\n")
    except Exception:
        pass

def get_db_connection(db_path: str = DB_PATH) -> sqlite3.Connection:
    conn = sqlite3.connect(db_path, timeout=10.0)
    conn.row_factory = sqlite3.Row
    return conn

def get_system_config(conn: sqlite3.Connection) -> dict:
    cur = conn.cursor()
    cur.execute("SELECT config_key, config_value FROM system_config")
    rows = cur.fetchall()
    return {row["config_key"]: row["config_value"] for row in rows}

def send_email_notification(config: dict, payload: dict) -> bool:
    to_email = config.get("notification_email_to", "").strip()
    if not to_email:
        log("Bỏ qua gửi email do chưa cấu hình notification_email_to.")
        return True

    smtp_host = config.get("smtp_host", "").strip()
    mock_mode = config.get("smtp_mock_mode", "0") == "1"

    order_code = payload.get("order_code", "UNKNOWN")
    shop_name = config.get("shop_name", "Bếp Chay An Lành")
    subject = f"[{shop_name}] Có đơn hàng mới #{order_code}"

    # Text summary
    total_amount = f"{payload.get('total_amount', 0):,}đ".replace(",", ".")
    cust_name = payload.get("customer_name", "Khách")
    cust_phone = payload.get("customer_phone", "")
    slot_time = payload.get("slot_time", "")
    slot_date = payload.get("slot_date", "")

    body_text = f"""Bếp Chay An Lành - CÓ ĐƠN HÀNG MỚI #{order_code}
Khung giờ: {slot_date} ({slot_time})
Khách hàng: {cust_name} ({cust_phone})
Tổng tiền: {total_amount}
Trạng thái: Chờ xác nhận
"""

    if mock_mode or not smtp_host:
        log(f"MOCK EMAIL to <{to_email}>: {subject} | {body_text.strip()}")
        return True

    smtp_port = int(config.get("smtp_port", 587))
    smtp_user = config.get("smtp_username", "").strip()
    smtp_pass = config.get("smtp_password", "").strip()
    smtp_enc = config.get("smtp_encryption", "tls").strip().lower()
    from_email = config.get("smtp_from_email", smtp_user or "bepchay@anyen.local").strip()

    msg = MIMEMultipart("alternative")
    msg["Subject"] = subject
    msg["From"] = from_email
    msg["To"] = to_email
    msg.attach(MIMEText(body_text, "plain", "utf-8"))

    if smtp_enc == "ssl":
        ctx = ssl.create_default_context()
        with smtplib.SMTP_SSL(smtp_host, smtp_port, context=ctx, timeout=15) as server:
            if smtp_user and smtp_pass:
                server.login(smtp_user, smtp_pass)
            server.sendmail(from_email, [to_email], msg.as_string())
    else:
        with smtplib.SMTP(smtp_host, smtp_port, timeout=15) as server:
            if smtp_enc == "tls":
                ctx = ssl.create_default_context()
                server.starttls(context=ctx)
            if smtp_user and smtp_pass:
                server.login(smtp_user, smtp_pass)
            server.sendmail(from_email, [to_email], msg.as_string())

    return True

def process_single_item(conn: sqlite3.Connection, config: dict, item: sqlite3.Row) -> bool:
    item_id = item["id"]
    channel = item["channel"]
    attempt = item["attempt_count"] + 1

    cur = conn.cursor()
    cur.execute("UPDATE notification_outbox SET status = 'processing' WHERE id = ?", (item_id,))
    conn.commit()

    payload = {}
    if item["payload_json"]:
        try:
            payload = json.loads(item["payload_json"])
        except Exception:
            pass

    try:
        success = False
        if channel == "email":
            success = send_email_notification(config, payload)
        elif channel == "web":
            success = True
        else:
            success = True

        if success:
            cur.execute("""
                UPDATE notification_outbox 
                SET status = 'sent', sent_at = CURRENT_TIMESTAMP, last_error = NULL 
                WHERE id = ?
            """, (item_id,))
            conn.commit()
            return True
        else:
            raise RuntimeError(f"Kênh {channel} gửi thất bại.")
    except Exception as e:
        err_msg = str(e)[:400]
        if attempt >= 5:
            cur.execute("""
                UPDATE notification_outbox 
                SET status = 'failed', attempt_count = ?, next_retry_at = NULL, last_error = ? 
                WHERE id = ?
            """, (attempt, err_msg, item_id))
        else:
            backoff_mins = {1: 1, 2: 5, 3: 15, 4: 60}.get(attempt, 60)
            next_time = (datetime.utcnow() + timedelta(minutes=backoff_mins)).strftime("%Y-%m-%d %H:%M:%S")
            cur.execute("""
                UPDATE notification_outbox 
                SET status = 'pending', attempt_count = ?, next_retry_at = ?, last_error = ? 
                WHERE id = ?
            """, (attempt, next_time, err_msg, item_id))
        conn.commit()
        return False

def run_worker_loop(run_once: bool, sleep_seconds: int, limit: int):
    log(f"Khởi động Python Worker (run_once={run_once}, sleep={sleep_seconds}s, limit={limit})")
    
    while True:
        try:
            conn = get_db_connection()
            config = get_system_config(conn)
            cur = conn.cursor()

            # Phục hồi các job kẹt processing quá 5 phút và lấy pending
            cur.execute("""
                SELECT * FROM notification_outbox
                WHERE (
                    status = 'pending' 
                    AND (next_retry_at IS NULL OR next_retry_at <= datetime('now'))
                )
                OR (
                    status = 'processing' 
                    AND created_at <= datetime('now', '-5 minutes')
                )
                ORDER BY id ASC
                LIMIT ?
            """, (limit,))
            rows = cur.fetchall()

            if rows:
                log(f"Tìm thấy {len(rows)} thông báo cần xử lý.")
                for row in rows:
                    log(f"-> Xử lý job #{row['id']} [{row['event_key']}] ({row['channel']})...")
                    ok = process_single_item(conn, config, row)
                    if ok:
                        log(f"   ✓ Job #{row['id']} hoàn thành.")
                    else:
                        log(f"   ✗ Job #{row['id']} lỗi, đã lập lịch thử lại.")
            conn.close()
        except Exception as e:
            log(f"Lỗi worker: {e}")

        if run_once:
            break
        time.sleep(sleep_seconds)

    log("Python Worker kết thúc.")

def main():
    parser = argparse.ArgumentParser(description="Notification Outbox Worker (Python)")
    parser.add_argument("--run-once", action="store_true", help="Chạy một lượt các job đang chờ rồi thoát")
    parser.add_argument("--daemon", action="store_true", help="Chạy chế độ daemon liên tục")
    parser.add_argument("--sleep", type=int, default=5, help="Thời gian nghỉ giữa các chu kỳ (giây)")
    parser.add_argument("--limit", type=int, default=20, help="Số lượng job tối đa mỗi chu kỳ")
    args = parser.parse_args()

    run_once = args.run_once or (not args.daemon)
    run_worker_loop(run_once=run_once, sleep_seconds=args.sleep, limit=args.limit)

if __name__ == "__main__":
    main()
