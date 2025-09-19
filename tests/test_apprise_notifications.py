import os
import sqlite3
import tempfile
from datetime import datetime
from unittest.mock import patch

import pytest

from scripts.utils.notifications import sendAppriseNotifications


def create_test_db(db_file):
    """create a database connection to a SQLite database"""
    conn = None
    try:
        conn = sqlite3.connect(db_file)
        sql_create_detections_table = """ CREATE TABLE IF NOT EXISTS detections (
                                        id integer PRIMARY KEY,
                                        Com_Name text NOT NULL,
                                        Date date NULL,
                                        Time time NULL,
                                        Probe text NULL
                                    ); """
        cur = conn.cursor()
        cur.execute(sql_create_detections_table)
        sql = """ INSERT INTO detections(Com_Name, Date)
              VALUES(?,?) """

        cur = conn.cursor()
        today = datetime.now().strftime("%Y-%m-%d")  # SQLite stores date as YYYY-MM-DD
        cur.execute(sql, ["Great Crested Flycatcher", today])
        conn.commit()

    except Exception as e:
        print(e)
    finally:
        if conn:
            conn.close()


@pytest.fixture(autouse=True)
def clean_up_after_each_test():
    yield
    if os.path.exists("test.db"):
        os.remove("test.db")


def test_notifications():
    create_test_db("test.db")
    settings_dict = {
        "APPRISE_NOTIFICATION_TITLE": "New backyard bird!",
        "APPRISE_NOTIFICATION_BODY": "A $comname ($sciname) was just detected with a confidence of $confidence ($reason)",
        "APPRISE_NOTIFY_EACH_DETECTION": "0",
        "APPRISE_NOTIFY_NEW_SPECIES": "0",
        "APPRISE_NOTIFY_NEW_SPECIES_EACH_DAY": "0",
        "APPRISE_MINIMUM_SECONDS_BETWEEN_NOTIFICATIONS_PER_SPECIES": "0",
    }

    with tempfile.TemporaryDirectory() as tmp_dir:
        config_path = os.path.join(tmp_dir, "apprise.txt")
        body_path = os.path.join(tmp_dir, "body.txt")
        with open(config_path, "w") as cfg:
            cfg.write("# mock config\n")
        with open(body_path, "w") as body_file:
            body_file.write(settings_dict["APPRISE_NOTIFICATION_BODY"])

        with patch("scripts.utils.notifications.APPRISE_CONFIG", config_path), patch(
            "scripts.utils.notifications.APPRISE_BODY", body_path
        ), patch("scripts.utils.notifications.notify") as notify_call:

            sendAppriseNotifications(
                "Myiarchus crinitus_Great Crested Flycatcher",
                "0.91",
                "91",
                "filename",
                "2025-07-07",
                "07:07:07",
                "07",
                "-1",
                "-1",
                "0.7",
                "1.25",
                "0.0",
                settings_dict,
                "test.db",
            )

            # No active apprise notifications configured. Confirm no notifications.
            assert notify_call.call_count == 0  # No notification should be sent.

            # Add daily notification.
            notify_call.reset_mock()
            settings_dict["APPRISE_NOTIFY_NEW_SPECIES_EACH_DAY"] = "1"
            sendAppriseNotifications(
                "Myiarchus crinitus_Great Crested Flycatcher",
                "0.91",
                "91",
                "filename",
                "2025-07-07",
                "07:07:07",
                "07",
                "-1",
                "-1",
                "0.7",
                "1.25",
                "0.0",
                settings_dict,
                "test.db",
            )

            assert notify_call.call_count == 1
            first_message = notify_call.call_args_list[0][0][0]
            assert "Great Crested Flycatcher" in first_message
            assert "first time today" in first_message

            # Add new species notification.
            notify_call.reset_mock()
            settings_dict["APPRISE_NOTIFY_NEW_SPECIES"] = "1"
            sendAppriseNotifications(
                "Myiarchus crinitus_Great Crested Flycatcher",
                "0.91",
                "91",
                "filename",
                "2025-07-07",
                "07:07:07",
                "07",
                "-1",
                "-1",
                "0.7",
                "1.25",
                "0.0",
                settings_dict,
                "test.db",
            )

            assert notify_call.call_count == 2
            first_message = notify_call.call_args_list[0][0][0]
            second_message = notify_call.call_args_list[1][0][0]
            assert "Great Crested Flycatcher" in first_message
            assert "first time today" in first_message
            assert "Great Crested Flycatcher" in second_message
            assert "only seen 1 times in last 7d" in second_message

            # Add each species notification.
            notify_call.reset_mock()
            settings_dict["APPRISE_NOTIFY_EACH_DETECTION"] = "1"
            sendAppriseNotifications(
                "Myiarchus crinitus_Great Crested Flycatcher",
                "0.91",
                "91",
                "filename",
                "2025-07-07",
                "07:07:07",
                "07",
                "-1",
                "-1",
                "0.7",
                "1.25",
                "0.0",
                settings_dict,
                "test.db",
            )

            assert notify_call.call_count == 3
