import sqlite3

def init_db():
    """
    Initialize the SQLite database for logging alerts.
    """
    conn = sqlite3.connect('data/drowning_alerts.db')
    c = conn.cursor()
    c.execute('''CREATE TABLE IF NOT EXISTS alerts
                 (id INTEGER PRIMARY KEY AUTOINCREMENT,
                  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                  alert_type TEXT,
                  details TEXT)''')
    conn.commit()
    conn.close()

def log_alert(alert_type, details):
    """
    Log an alert to the database.
    """
    conn = sqlite3.connect('data/drowning_alerts.db')
    c = conn.cursor()
    c.execute("INSERT INTO alerts (alert_type, details) VALUES (?, ?)",
              (alert_type, details))
    conn.commit()
    conn.close()

def get_recent_alerts(limit=10):
    """
    Retrieve recent alerts from the database.
    """
    conn = sqlite3.connect('data/drowning_alerts.db')
    c = conn.cursor()
    c.execute("SELECT * FROM alerts ORDER BY timestamp DESC LIMIT ?", (limit,))
    alerts = c.fetchall()
    conn.close()
    return alerts

def view_alerts():
    """
    Print recent alerts for debugging.
    """
    alerts = get_recent_alerts()
    if alerts:
        print("Recent Alerts:")
        for alert in alerts:
            print(f"{alert[1]} - {alert[2]}: {alert[3]}")
    else:
        print("No alerts logged yet.")

# Initialize database on module import
init_db()

if __name__ == "__main__":
    # Test the database functions
    log_alert("TEST", "Database test alert")
    view_alerts()