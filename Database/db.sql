/*
SQLyog Community v13.3.1 (64 bit)
MySQL - 10.4.32-MariaDB : Database - midsec
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`midsec` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `midsec`;

/*Table structure for table `audit_log` */

DROP TABLE IF EXISTS `audit_log`;

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_table` varchar(50) NOT NULL,
  `target_id` int(11) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_actor` (`actor_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_table` (`target_table`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_action_date` (`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `audit_log` */

/*Table structure for table `cctv_feeds` */

DROP TABLE IF EXISTS `cctv_feeds`;

CREATE TABLE `cctv_feeds` (
  `feed_id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_name` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `stream_url` varchar(255) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `feed_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`feed_id`),
  UNIQUE KEY `uq_feed_name` (`feed_name`),
  KEY `idx_feed_status` (`feed_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `cctv_feeds` */

/*Table structure for table `incidents` */

DROP TABLE IF EXISTS `incidents`;

CREATE TABLE `incidents` (
  `incident_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `assigned_guard_id` int(11) DEFAULT NULL,
  `cctv_feed_id` int(11) DEFAULT NULL,
  `incident_type` enum('suspicious_activity','theft_breakin','property_damage') NOT NULL,
  `description` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('open','under_review','resolved') NOT NULL DEFAULT 'open',
  `guard_note` text DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`incident_id`),
  KEY `idx_incident_user` (`user_id`),
  KEY `idx_incident_guard` (`assigned_guard_id`),
  KEY `idx_incident_cctv` (`cctv_feed_id`),
  KEY `idx_incident_status` (`status`),
  KEY `idx_incident_deleted` (`is_deleted`),
  KEY `idx_active_by_status` (`is_deleted`,`status`),
  KEY `idx_incident_reported_month` (`reported_at`),
  KEY `idx_incident_type_status` (`incident_type`,`status`,`is_deleted`),
  CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`assigned_guard_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `incidents_ibfk_3` FOREIGN KEY (`cctv_feed_id`) REFERENCES `cctv_feeds` (`feed_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `incidents` */

/*Table structure for table `panic_alerts` */

DROP TABLE IF EXISTS `panic_alerts`;

CREATE TABLE `panic_alerts` (
  `alert_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `guard_id` int(11) DEFAULT NULL,
  `cctv_feed_id` int(11) DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `status` enum('active','responded','closed') NOT NULL DEFAULT 'active',
  `triggered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`alert_id`),
  KEY `idx_panic_user` (`user_id`),
  KEY `idx_panic_cctv` (`cctv_feed_id`),
  KEY `idx_panic_guard` (`guard_id`),
  KEY `idx_panic_status` (`status`),
  KEY `idx_panic_triggered_month` (`triggered_at`),
  CONSTRAINT `fk_panic_guard` FOREIGN KEY (`guard_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `panic_alerts_ibfk1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `panic_alerts_ibfk3` FOREIGN KEY (`cctv_feed_id`) REFERENCES `cctv_feeds` (`feed_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `panic_alerts` */

/*Table structure for table `payments` */

DROP TABLE IF EXISTS `payments`;

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 500.00,
  `mpesa_ref` varchar(50) NOT NULL,
  `payment_status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `subscription_start` date DEFAULT NULL,
  `subscription_end` date DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `uq_mpesa_ref` (`mpesa_ref`),
  KEY `idx_payment_user` (`user_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_payment_paid_month` (`paid_at`,`payment_status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `payments` */

/*Table structure for table `police_escalations` */

DROP TABLE IF EXISTS `police_escalations`;

CREATE TABLE `police_escalations` (
  `escalation_id` int(11) NOT NULL AUTO_INCREMENT,
  `incident_id` int(11) NOT NULL,
  `escalated_by` int(11) NOT NULL,
  `station_name` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `reference_code` varchar(50) DEFAULT NULL,
  `escalated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`escalation_id`),
  KEY `idx_esc_incident` (`incident_id`),
  KEY `idx_esc_escalated_by` (`escalated_by`),
  KEY `idx_escalation_month` (`escalated_at`),
  CONSTRAINT `pe_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`),
  CONSTRAINT `pe_ibfk_2` FOREIGN KEY (`escalated_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `police_escalations` */

/*Table structure for table `sms_logs` */

DROP TABLE IF EXISTS `sms_logs`;

CREATE TABLE `sms_logs` (
  `sms_id` int(11) NOT NULL AUTO_INCREMENT,
  `incident_id` int(11) NOT NULL,
  `guard_id` int(11) NOT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `delivery_status` enum('sent','delivered','failed') NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sms_id`),
  KEY `idx_sms_incident` (`incident_id`),
  KEY `idx_sms_guard` (`guard_id`),
  KEY `idx_sms_sent_month` (`sent_at`,`delivery_status`),
  CONSTRAINT `sms_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`),
  CONSTRAINT `sms_ibfk_2` FOREIGN KEY (`guard_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `sms_logs` */

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_no` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('resident','guard','admin') NOT NULL DEFAULT 'resident',
  `house_no` varchar(10) NOT NULL DEFAULT '',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_phone_no` (`phone_no`),
  KEY `idx_role` (`role`),
  KEY `idx_email_status` (`email`,`status`),
  KEY `idx_verification_status` (`verification_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `users` */

/* Procedure structure for procedure `sp_add_cctv_feed` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_add_cctv_feed` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_add_cctv_feed`(
    IN p_admin_id   INT,
    IN p_feed_name  VARCHAR(100),
    IN p_location   VARCHAR(100),
    IN p_stream_url VARCHAR(255),
    IN p_latitude   DECIMAL(10,8),
    IN p_longitude  DECIMAL(11,8)
)
BEGIN
    DECLARE v_new_feed_id INT DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only admins can add CCTV feeds.';
    END IF;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_feed_name IS NULL OR TRIM(p_feed_name) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Feed name is required.';
    END IF;

    IF p_location IS NULL OR TRIM(p_location) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Feed location is required.';
    END IF;

    IF p_stream_url IS NULL OR TRIM(p_stream_url) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Stream URL is required.';
    END IF;

    IF p_latitude IS NULL OR p_longitude IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'GPS coordinates are required.';
    END IF;

    -- --------------------------------------------------------
    -- Duplicate feed name check
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM cctv_feeds
        WHERE feed_name = TRIM(p_feed_name)
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A CCTV feed with this name already exists.';
    END IF;

    -- --------------------------------------------------------
    -- Duplicate stream URL check
    -- Two feeds must not share the same RTSP endpoint.
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM cctv_feeds
        WHERE stream_url = TRIM(p_stream_url)
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A CCTV feed with this stream URL already exists.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic insert + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        INSERT INTO cctv_feeds (
            feed_name,
            location,
            stream_url,
            latitude,
            longitude,
            feed_status
        )
        VALUES (
            TRIM(p_feed_name),
            TRIM(p_location),
            TRIM(p_stream_url),
            p_latitude,
            p_longitude,
            'active'
        );

        SET v_new_feed_id = LAST_INSERT_ID();

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_admin_id,
            'ADD_CCTV_FEED',
            'cctv_feeds',
            v_new_feed_id,
            JSON_OBJECT(
                'feed_name',  TRIM(p_feed_name),
                'location',   TRIM(p_location),
                'feed_status','active',
                'latitude',   p_latitude,
                'longitude',  p_longitude
                -- stream_url intentionally excluded from audit log
                -- to prevent RTSP credentials leaking into audit records
            )
        );

    COMMIT;

    -- Return feed details — admin-appropriate response
    -- stream_url included here as admin initiated the creation
    SELECT
        v_new_feed_id   AS feed_id,
        p_feed_name     AS feed_name,
        p_location      AS location,
        p_stream_url    AS stream_url,
        p_latitude      AS latitude,
        p_longitude     AS longitude,
        'active'        AS feed_status,
        'CCTV feed added successfully.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_check_subscription` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_check_subscription` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_check_subscription`(
    IN p_user_id INT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: verified, active resident only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_user_id
          AND role                = 'resident'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Verified active resident account required.';
    END IF;

    -- --------------------------------------------------------
    -- Return subscription status — minimum required fields
    -- mpesa_ref intentionally excluded (data minimization)
    -- amount intentionally excluded (use sp_get_payment_history)
    -- --------------------------------------------------------
    SELECT
        payment_id,
        payment_status,
        subscription_start,
        subscription_end,
        -- Derived status for UI display
        CASE
            WHEN payment_status  = 'completed'
             AND subscription_end >= CURDATE()
            THEN 'active'
            WHEN payment_status  = 'completed'
             AND subscription_end <  CURDATE()
            THEN 'expired'
            WHEN payment_status  = 'pending'
            THEN 'payment_pending'
            ELSE 'inactive'
        END AS subscription_status,
        -- Days remaining: negative means expired
        CASE
            WHEN subscription_end IS NOT NULL
            THEN DATEDIFF(subscription_end, CURDATE())
            ELSE NULL
        END AS days_remaining,
        paid_at
    FROM payments
    WHERE user_id = p_user_id
    ORDER BY paid_at DESC
    LIMIT 1;

    -- If no payment record exists at all, return a clear
    -- no-subscription message as a second result set.
    -- PHP can check if the first result set is empty.

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_close_escalation` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_close_escalation` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_close_escalation`(
    IN p_admin_id      INT,
    IN p_escalation_id INT,
    IN p_outcome_notes TEXT   -- mandatory: what was the final police outcome
)
BEGIN
    DECLARE v_incident_id    INT         DEFAULT NULL;
    DECLARE v_reference_code VARCHAR(50) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only admins can close escalations.';
    END IF;

    -- --------------------------------------------------------
    -- Outcome notes are mandatory
    -- Admin must document the final police response outcome
    -- before the escalation can be closed.
    -- --------------------------------------------------------
    IF p_outcome_notes IS NULL OR TRIM(p_outcome_notes) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Outcome notes are required. Please document the final police response before closing this escalation.';
    END IF;

    -- --------------------------------------------------------
    -- Validate escalation exists
    -- --------------------------------------------------------
    SELECT incident_id, reference_code
    INTO   v_incident_id, v_reference_code
    FROM   police_escalations
    WHERE  escalation_id = p_escalation_id;

    IF v_incident_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Escalation record not found.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic closure: resolve incident + dual audit log entries
    -- --------------------------------------------------------
    START TRANSACTION;

        -- Mark linked incident as resolved
        UPDATE incidents
        SET status     = 'resolved',
            updated_at = NOW()
        WHERE incident_id  = v_incident_id
          AND status       <> 'resolved';

        -- Audit log: escalation closure
        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_admin_id,
            'CLOSE_ESCALATION',
            'police_escalations',
            p_escalation_id,
            JSON_OBJECT(
                'reference_code', v_reference_code,
                'outcome_notes',  p_outcome_notes
            )
        );

        -- Audit log: incident resolution
        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_admin_id,
            'RESOLVE_INCIDENT_VIA_ESCALATION_CLOSE',
            'incidents',
            v_incident_id,
            JSON_OBJECT(
                'status',        'resolved',
                'escalation_id', p_escalation_id
            )
        );

    COMMIT;

    SELECT
        p_escalation_id  AS escalation_id,
        v_reference_code AS reference_code,
        v_incident_id    AS linked_incident_id,
        p_outcome_notes  AS outcome_notes,
        'Escalation closed and incident resolved. Outcome documented in audit trail.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_close_panic` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_close_panic` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_close_panic`(
    IN p_closed_by         INT,   -- guard or admin user_id
    IN p_alert_id          INT,
    IN p_resolution_notes  TEXT   -- mandatory: what happened, how was it resolved
)
BEGIN
    DECLARE v_old_status VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- CRITICAL: Caller must be guard or admin
    -- This was missing entirely in the original procedure.
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_closed_by
          AND role                IN ('guard','admin')
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only guards and admins can close panic alerts.';
    END IF;

    -- --------------------------------------------------------
    -- Resolution notes are mandatory
    -- Guards must document what happened before closing.
    -- --------------------------------------------------------
    IF p_resolution_notes IS NULL OR TRIM(p_resolution_notes) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Resolution notes are required. Please document the outcome before closing this alert.';
    END IF;

    -- --------------------------------------------------------
    -- Validate alert: must exist
    -- --------------------------------------------------------
    SELECT status INTO v_old_status
    FROM   panic_alerts
    WHERE  alert_id = p_alert_id;

    IF v_old_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Panic alert not found.';
    END IF;

    IF v_old_status = 'closed' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'This panic alert is already closed.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic close + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        UPDATE panic_alerts
        SET status   = 'closed',
            guard_id = COALESCE(guard_id, p_closed_by)
            -- If no guard was previously assigned (e.g. admin closing
            -- an orphaned active alert), record the closer as guard.
        WHERE alert_id = p_alert_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            p_closed_by,
            'CLOSE_PANIC',
            'panic_alerts',
            p_alert_id,
            JSON_OBJECT('status', v_old_status),
            JSON_OBJECT(
                'status',           'closed',
                'closed_by',        p_closed_by,
                'resolution_notes', p_resolution_notes
            )
        );

    COMMIT;

    SELECT
        p_alert_id AS alert_id,
        'closed'   AS status,
        p_resolution_notes AS resolution_notes,
        'Panic alert closed and outcome documented.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_create_incident` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_create_incident` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_incident`(
    IN p_user_id       INT,
    IN p_incident_type ENUM('suspicious_activity','theft_breakin','property_damage'),
    IN p_description   TEXT,
    IN p_latitude      DECIMAL(10,8),
    IN p_longitude     DECIMAL(11,8)
)
BEGIN
    DECLARE v_feed_id     INT     DEFAULT NULL;
    DECLARE v_incident_id INT     DEFAULT NULL;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'User ID is required.';
    END IF;

    IF p_incident_type IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident type is required.';
    END IF;

    IF p_latitude IS NULL OR p_longitude IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'GPS coordinates are required.';
    END IF;

    -- --------------------------------------------------------
    -- Caller authorization:
    -- Must be a verified, active resident
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_user_id
          AND role                = 'resident'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. A verified active resident account is required to report incidents.';
    END IF;

    -- --------------------------------------------------------
    -- Auto-assign nearest active CCTV feed
    -- Uses Pythagorean approximation on DECIMAL coordinates.
    -- NULL result is acceptable — cctv_feed_id is nullable.
    -- --------------------------------------------------------
    SELECT feed_id
    INTO   v_feed_id
    FROM   cctv_feeds
    WHERE  feed_status = 'active'
    ORDER BY (
        POW(latitude  - p_latitude,  2) +
        POW(longitude - p_longitude, 2)
    ) ASC
    LIMIT 1;

    -- --------------------------------------------------------
    -- Atomic insert + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        INSERT INTO incidents (
            user_id,
            cctv_feed_id,
            incident_type,
            description,
            latitude,
            longitude,
            status,
            reported_at
        )
        VALUES (
            p_user_id,
            v_feed_id,           -- NULL if no active CCTV exists
            p_incident_type,
            p_description,
            p_latitude,
            p_longitude,
            'open',
            NOW()
        );

        SET v_incident_id = LAST_INSERT_ID();

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_user_id,
            'CREATE_INCIDENT',
            'incidents',
            v_incident_id,
            JSON_OBJECT(
                'incident_type', p_incident_type,
                'status',        'open',
                'cctv_feed_id',  v_feed_id
            )
        );

    COMMIT;

    -- --------------------------------------------------------
    -- Response: resident-safe fields only
    -- stream_url is NOT included — internal infrastructure only.
    -- resident_phone NOT included — resident knows their own number.
    -- --------------------------------------------------------
    SELECT
        i.incident_id,
        i.incident_type,
        i.description,
        i.latitude,
        i.longitude,
        i.status,
        i.reported_at,
        CONCAT(u.first_name, ' ', u.last_name) AS resident_name,
        u.house_no,
        c.feed_name   AS cctv_feed,     -- name only; not stream_url
        c.location    AS cctv_location,
        'Incident reported successfully.' AS message
    FROM  incidents i
    JOIN  users      u ON i.user_id      = u.user_id
    LEFT JOIN cctv_feeds c ON i.cctv_feed_id = c.feed_id
    WHERE i.incident_id = v_incident_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_create_payment` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_create_payment` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_payment`(
    IN p_user_id   INT,
    IN p_amount    DECIMAL(10,2),
    IN p_mpesa_ref VARCHAR(50)   -- Safaricom CheckoutRequestID at this stage
)
BEGIN
    DECLARE v_new_payment_id INT DEFAULT NULL;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'User ID is required.';
    END IF;

    IF p_amount IS NULL OR p_amount <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A valid payment amount is required.';
    END IF;

    IF p_mpesa_ref IS NULL OR TRIM(p_mpesa_ref) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'M-Pesa reference is required.';
    END IF;

    -- --------------------------------------------------------
    -- Caller authorization: verified, active resident only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_user_id
          AND role                = 'resident'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. A verified active resident account is required.';
    END IF;

    -- --------------------------------------------------------
    -- Duplicate mpesa_ref check (replay attack prevention)
    -- If the same CheckoutRequestID is submitted twice,
    -- reject the second attempt — it is either a duplicate
    -- request or a replay attack.
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM payments
        WHERE mpesa_ref = TRIM(p_mpesa_ref)
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'This M-Pesa reference has already been recorded. If you believe this is an error, please contact the estate admin.';
    END IF;

    -- --------------------------------------------------------
    -- Prevent multiple simultaneous pending payments
    -- A resident should not have more than one payment in
    -- 'pending' state at a time. This prevents confusion
    -- if the Safaricom callback is delayed and the resident
    -- attempts to pay again.
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM payments
        WHERE user_id        = p_user_id
          AND payment_status = 'pending'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'You have a pending payment already in progress. Please wait for the M-Pesa confirmation before initiating a new payment.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic insert + audit log
    -- CRITICAL: payment_status = 'pending', NOT 'completed'.
    -- subscription_start and subscription_end are NULL here.
    -- They are set ONLY by sp_update_payment_status after
    -- Safaricom confirms the transaction via callback.
    -- --------------------------------------------------------
    START TRANSACTION;

        INSERT INTO payments (
            user_id,
            amount,
            mpesa_ref,
            payment_status,
            subscription_start,  -- NULL until callback confirms
            subscription_end     -- NULL until callback confirms
        )
        VALUES (
            p_user_id,
            p_amount,
            TRIM(p_mpesa_ref),
            'pending',           -- NEVER 'completed' on creation
            NULL,
            NULL
        );

        SET v_new_payment_id = LAST_INSERT_ID();

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_user_id,
            'CREATE_PAYMENT',
            'payments',
            v_new_payment_id,
            JSON_OBJECT(
                'amount',         p_amount,
                'payment_status', 'pending'
                -- mpesa_ref excluded from audit log (financial identifier)
            )
        );

    COMMIT;

    -- Return pending confirmation to resident
    -- mpesa_ref included here so the resident/app can track
    -- this specific payment attempt
    SELECT
        v_new_payment_id AS payment_id,
        p_amount         AS amount,
        p_mpesa_ref      AS mpesa_ref,
        'pending'        AS payment_status,
        'Payment initiated. Please complete the M-Pesa prompt on your phone. Your subscription will activate once payment is confirmed.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_create_staff_user` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_create_staff_user` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_staff_user`(
    IN p_admin_id      INT,
    IN p_first_name    VARCHAR(100),
    IN p_last_name     VARCHAR(100),
    IN p_email         VARCHAR(255),
    IN p_phone_no      VARCHAR(20),
    IN p_password_hash VARCHAR(255),
    IN p_role          ENUM('guard','admin'),
    IN p_house_no      VARCHAR(10)
)
BEGIN
    DECLARE v_new_user_id INT DEFAULT 0;

    -- --------------------------------------------------------
    -- Caller authorization check
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only active verified admins can create staff accounts.';
    END IF;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_first_name IS NULL OR TRIM(p_first_name) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'First name is required.';
    END IF;

    IF p_email IS NULL OR TRIM(p_email) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Email address is required.';
    END IF;

    IF p_role IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Role is required (guard or admin).';
    END IF;

    -- --------------------------------------------------------
    -- Duplicate checks
    -- --------------------------------------------------------
    IF EXISTS (SELECT 1 FROM users WHERE email = LOWER(TRIM(p_email))) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'An account with this email address already exists.';
    END IF;

    IF EXISTS (SELECT 1 FROM users WHERE phone_no = TRIM(p_phone_no)) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'An account with this phone number already exists.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic insert + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        INSERT INTO users (
            first_name,
            last_name,
            email,
            phone_no,
            password_hash,
            role,
            house_no,
            status,
            verification_status
        )
        VALUES (
            TRIM(p_first_name),
            TRIM(p_last_name),
            LOWER(TRIM(p_email)),
            TRIM(p_phone_no),
            p_password_hash,
            p_role,
            UPPER(TRIM(p_house_no)),
            'active',
            'verified'  -- staff accounts are immediately active
        );

        SET v_new_user_id = LAST_INSERT_ID();

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_admin_id,
            'CREATE_STAFF_USER',
            'users',
            v_new_user_id,
            JSON_OBJECT(
                'role',  p_role,
                'email', LOWER(TRIM(p_email))
            )
        );

    COMMIT;

    SELECT
        v_new_user_id AS user_id,
        CONCAT(p_role, ' account created successfully.') AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_delete_cctv_feed` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_delete_cctv_feed` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_delete_cctv_feed`(
    IN p_admin_id INT,
    IN p_feed_id  INT,
    IN p_reason   TEXT
)
BEGIN
    DECLARE v_feed_name       VARCHAR(100) DEFAULT NULL;
    DECLARE v_feed_status     VARCHAR(20)  DEFAULT NULL;
    DECLARE v_incident_count  INT          DEFAULT 0;
    DECLARE v_panic_count     INT          DEFAULT 0;

    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only admins can delete CCTV feeds.';
    END IF;

    -- --------------------------------------------------------
    -- Reason is mandatory
    -- --------------------------------------------------------
    IF p_reason IS NULL OR TRIM(p_reason) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A documented reason is required when deleting a CCTV feed.';
    END IF;

    -- --------------------------------------------------------
    -- Validate feed exists
    -- --------------------------------------------------------
    SELECT feed_name, feed_status
    INTO   v_feed_name, v_feed_status
    FROM   cctv_feeds
    WHERE  feed_id = p_feed_id;

    IF v_feed_name IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CCTV feed not found.';
    END IF;

    -- --------------------------------------------------------
    -- Feed must be deactivated before deletion
    -- Forces admin to explicitly take it offline first,
    -- ensuring the operational impact has been acknowledged.
    -- --------------------------------------------------------
    IF v_feed_status = 'active' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Feed must be deactivated before deletion. Use sp_toggle_cctv_feed_status first.';
    END IF;

    -- --------------------------------------------------------
    -- Referential integrity: block if incidents reference this feed
    -- Includes soft-deleted incidents (is_deleted = 0 OR 1)
    -- because they are retained as security evidence.
    -- --------------------------------------------------------
    SELECT COUNT(*) INTO v_incident_count
    FROM   incidents
    WHERE  cctv_feed_id = p_feed_id;

    IF v_incident_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot delete. This feed is referenced by existing incident records. Deactivate it instead to preserve evidence integrity.';
    END IF;

    -- --------------------------------------------------------
    -- Referential integrity: block if panic alerts reference feed
    -- --------------------------------------------------------
    SELECT COUNT(*) INTO v_panic_count
    FROM   panic_alerts
    WHERE  cctv_feed_id = p_feed_id;

    IF v_panic_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot delete. This feed is referenced by existing panic alert records. Deactivate it instead to preserve evidence integrity.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic delete + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        -- Audit log BEFORE delete (feed_id no longer exists after)
        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value
        )
        VALUES (
            p_admin_id,
            'DELETE_CCTV_FEED',
            'cctv_feeds',
            p_feed_id,
            JSON_OBJECT(
                'feed_name', v_feed_name,
                'reason',    p_reason
                -- stream_url excluded from audit log
            )
        );

        DELETE FROM cctv_feeds WHERE feed_id = p_feed_id;

    COMMIT;

    SELECT
        p_feed_id   AS feed_id,
        v_feed_name AS feed_name,
        'CCTV feed permanently deleted.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_delete_incident` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_delete_incident` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_delete_incident`(
    IN p_admin_id    INT,
    IN p_incident_id INT,
    IN p_reason      TEXT    -- admin must provide a documented reason
)
BEGIN
    DECLARE v_incident_status VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only admins can delete incidents.';
    END IF;

    -- --------------------------------------------------------
    -- Reason is mandatory — admin must document why
    -- --------------------------------------------------------
    IF p_reason IS NULL OR TRIM(p_reason) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A documented reason is required when deleting an incident.';
    END IF;

    -- --------------------------------------------------------
    -- Validate incident: must exist and not already deleted
    -- --------------------------------------------------------
    SELECT status INTO v_incident_status
    FROM   incidents
    WHERE  incident_id = p_incident_id
      AND  is_deleted  = 0;

    IF v_incident_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident not found or already deleted.';
    END IF;

    -- --------------------------------------------------------
    -- Block deletion if police escalations exist
    -- Escalated incidents are part of an active police record.
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM police_escalations
        WHERE incident_id = p_incident_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot delete. This incident has active police escalations. Contact the relevant authority first.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic soft delete + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        UPDATE incidents
        SET is_deleted = 1,
            deleted_by = p_admin_id,
            deleted_at = NOW(),
            updated_at = NOW()
        WHERE incident_id = p_incident_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            p_admin_id,
            'SOFT_DELETE_INCIDENT',
            'incidents',
            p_incident_id,
            JSON_OBJECT('status', v_incident_status, 'is_deleted', 0),
            JSON_OBJECT(
                'is_deleted', 1,
                'reason',     p_reason
            )
        );

    COMMIT;

    SELECT
        p_incident_id AS incident_id,
        'Incident removed from active view. Record retained for audit and compliance.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_escalate_to_police` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_escalate_to_police` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_escalate_to_police`(
    IN p_incident_id  INT,
    IN p_escalated_by INT,
    IN p_station_name VARCHAR(100),
    IN p_notes        TEXT
)
BEGIN
    DECLARE v_escalation_id  INT         DEFAULT NULL;
    DECLARE v_reference_code VARCHAR(50) DEFAULT NULL;
    DECLARE v_caller_role    VARCHAR(20) DEFAULT NULL;
    DECLARE v_incident_type  VARCHAR(50) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_incident_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident ID is required.';
    END IF;

    IF p_station_name IS NULL OR TRIM(p_station_name) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Police station name is required.';
    END IF;

    IF p_notes IS NULL OR TRIM(p_notes) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Escalation notes are required. Please document why police involvement is needed.';
    END IF;

    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    SELECT role INTO v_caller_role
    FROM   users
    WHERE  user_id             = p_escalated_by
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_caller_role IS NULL OR
       v_caller_role NOT IN ('guard','admin')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only guards and admins can escalate incidents to police.';
    END IF;

    -- --------------------------------------------------------
    -- Validate incident: must exist and not be soft-deleted
    -- --------------------------------------------------------
    SELECT incident_type INTO v_incident_type
    FROM   incidents
    WHERE  incident_id = p_incident_id
      AND  is_deleted  = 0;

    IF v_incident_type IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident not found or has been deleted.';
    END IF;

    -- --------------------------------------------------------
    -- Duplicate escalation prevention
    -- One incident should have at most one escalation record.
    -- If police response is needed again, the existing
    -- escalation status should be updated via
    -- sp_update_escalation_status.
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM police_escalations
        WHERE  incident_id = p_incident_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'This incident has already been escalated to police. Use sp_update_escalation_status to update the escalation status.';
    END IF;

    -- --------------------------------------------------------
    -- Generate non-predictable reference code
    -- Format: ESC-YYYY-[4 random hex chars]-[4 random hex chars]
    -- Example: ESC-2025-A3F7-9C2E
    --
    -- The random hex segments prevent enumeration of reference
    -- codes. An attacker who intercepts one reference code
    -- cannot predict or derive any other valid reference code.
    --
    -- SUBSTRING(MD5(RAND()), 1, 4) generates 4 random hex
    -- characters using MariaDB's built-in functions — no
    -- external UUID library required.
    -- --------------------------------------------------------
    SET v_reference_code = CONCAT(
        'ESC-',
        YEAR(NOW()),
        '-',
        UPPER(SUBSTRING(MD5(RAND()), 1, 4)),
        '-',
        UPPER(SUBSTRING(MD5(RAND()), 1, 4))
    );

    -- --------------------------------------------------------
    -- Atomic insert + incident status update + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        INSERT INTO police_escalations (
            incident_id,
            escalated_by,
            station_name,
            notes,
            reference_code,
            escalated_at
        )
        VALUES (
            p_incident_id,
            p_escalated_by,
            TRIM(p_station_name),
            p_notes,
            v_reference_code,
            NOW()
        );

        SET v_escalation_id = LAST_INSERT_ID();

        -- Update incident status to reflect police involvement
        UPDATE incidents
        SET status     = 'under_review',
            updated_at = NOW()
        WHERE incident_id = p_incident_id
          AND status      = 'open';  -- only update if still open

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_escalated_by,
            'ESCALATE_TO_POLICE',
            'police_escalations',
            v_escalation_id,
            JSON_OBJECT(
                'incident_id',   p_incident_id,
                'station_name',  TRIM(p_station_name),
                'reference_code',v_reference_code
                -- notes excluded (may contain sensitive details)
            )
        );

    COMMIT;

    SELECT
        v_escalation_id  AS escalation_id,
        v_reference_code AS reference_code,
        p_station_name   AS station_name,
        p_incident_id    AS incident_id,
        'Incident successfully escalated to police. Reference code generated.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_generate_report_record` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_generate_report_record` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_report_record`(
    IN p_admin_id    INT,
    IN p_report_type ENUM(
        'incident_summary',
        'crime_trend',
        'panic_statistics',
        'resident_activity',
        'guard_performance',
        'subscription_summary',
        'full_security_summary'
    ),
    IN p_date_from   DATE,
    IN p_date_to     DATE,
    IN p_file_path   VARCHAR(255)  -- NULL if report not exported to file
)
BEGIN
    DECLARE v_new_report_id INT DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    IF p_report_type IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Report type is required.';
    END IF;

    IF p_date_from IS NULL OR p_date_to IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Date range is required.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic insert + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        INSERT INTO reports (
            generated_by,
            report_type,
            date_range_start,
            date_range_end,
            file_path,
            created_at
        )
        VALUES (
            p_admin_id,
            p_report_type,
            p_date_from,
            p_date_to,
            p_file_path,
            NOW()
        );

        SET v_new_report_id = LAST_INSERT_ID();

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_admin_id,
            'GENERATE_REPORT',
            'reports',
            v_new_report_id,
            JSON_OBJECT(
                'report_type', p_report_type,
                'date_from',   p_date_from,
                'date_to',     p_date_to,
                'has_file',    (p_file_path IS NOT NULL)
            )
        );

    COMMIT;

    SELECT
        v_new_report_id AS report_id,
        p_report_type   AS report_type,
        p_date_from     AS date_from,
        p_date_to       AS date_to,
        'Report record generated and logged successfully.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_active_panics` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_active_panics` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_active_panics`(
    IN p_requesting_user_id INT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_requesting_user_id
          AND role                IN ('guard','admin')
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guards and admins only.';
    END IF;

    SELECT
        pa.alert_id,
        pa.latitude,
        pa.longitude,
        pa.status,
        pa.triggered_at,
        -- Time elapsed since alert was triggered (operational urgency indicator)
        TIMESTAMPDIFF(MINUTE, pa.triggered_at, NOW()) AS minutes_elapsed,
        CONCAT(u.first_name, ' ', u.last_name) AS resident_name,
        u.house_no,
        u.phone_no   AS resident_phone,   -- guards need this immediately
        c.feed_name  AS cctv_feed,
        c.stream_url AS cctv_url,         -- guards need live feed
        c.location   AS cctv_location
    FROM  panic_alerts pa
    JOIN  users u ON pa.user_id = u.user_id
    LEFT JOIN cctv_feeds c ON pa.cctv_feed_id = c.feed_id
    WHERE pa.status = 'active'
    ORDER BY pa.triggered_at ASC;  -- oldest first: longest-waiting resident at top

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_all_cctv_feeds` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_all_cctv_feeds` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_all_cctv_feeds`(
    IN p_requesting_user_id INT,
    IN p_status_filter      VARCHAR(20)  -- NULL = all; 'active'/'inactive'
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Verify requester is active and verified
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Active verified account required.';
    END IF;

    -- --------------------------------------------------------
    -- Role-aware response
    -- stream_url returned only to guard and admin
    -- --------------------------------------------------------
    SELECT
        feed_id,
        feed_name,
        location,
        latitude,
        longitude,
        feed_status,
        created_at,
        -- stream_url: guard and admin only
        CASE
            WHEN v_requester_role IN ('guard','admin') THEN stream_url
            ELSE NULL
        END AS stream_url
    FROM cctv_feeds
    WHERE (p_status_filter IS NULL OR feed_status = p_status_filter)
    ORDER BY feed_name ASC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_all_incidents` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_all_incidents` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_all_incidents`(
    IN p_requesting_user_id INT,
    IN p_status             VARCHAR(20),  -- NULL = all statuses
    IN p_incident_type      VARCHAR(30)   -- NULL = all types
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_requesting_user_id
          AND role                IN ('guard','admin')
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guards and admins only.';
    END IF;

    SELECT
        i.incident_id,
        i.incident_type,
        i.description,
        i.latitude,
        i.longitude,
        i.status,
        i.guard_note,
        i.reported_at,
        i.updated_at,
        CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
        r.house_no,
        r.phone_no    AS resident_phone,  -- guards need this to contact resident
        CONCAT(g.first_name, ' ', g.last_name) AS guard_name,
        c.feed_name   AS cctv_feed,       -- name only; not stream_url
        c.location    AS cctv_location
    FROM  incidents i
    JOIN  users      r  ON i.user_id           = r.user_id
    LEFT JOIN users  g  ON i.assigned_guard_id = g.user_id
    LEFT JOIN cctv_feeds c ON i.cctv_feed_id   = c.feed_id
    WHERE i.is_deleted = 0
      AND (p_status        IS NULL OR i.status        = p_status)
      AND (p_incident_type IS NULL OR i.incident_type = p_incident_type)
    ORDER BY i.reported_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_all_payments` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_all_payments` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_all_payments`(
    IN p_admin_id      INT,
    IN p_status_filter VARCHAR(20),  -- NULL = all statuses
    IN p_date_from     DATE,         -- NULL = no start filter
    IN p_date_to       DATE          -- NULL = no end filter
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    SELECT
        p.payment_id,
        p.amount,
        p.mpesa_ref,
        p.payment_status,
        p.subscription_start,
        p.subscription_end,
        p.paid_at,
        CASE
            WHEN p.payment_status  = 'completed'
             AND p.subscription_end >= CURDATE()
            THEN 'active'
            WHEN p.payment_status  = 'completed'
             AND p.subscription_end <  CURDATE()
            THEN 'expired'
            WHEN p.payment_status  = 'pending'
            THEN 'payment_pending'
            ELSE 'failed'
        END AS subscription_status,
        CONCAT(u.first_name, ' ', u.last_name) AS resident_name,
        u.house_no,
        u.phone_no  AS resident_phone,
        u.email     AS resident_email
    FROM  payments p
    JOIN  users u ON p.user_id = u.user_id
    WHERE (p_status_filter IS NULL OR p.payment_status = p_status_filter)
      AND (p_date_from     IS NULL OR DATE(p.paid_at) >= p_date_from)
      AND (p_date_to       IS NULL OR DATE(p.paid_at) <= p_date_to)
    ORDER BY p.paid_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_all_sms_logs` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_all_sms_logs` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_all_sms_logs`(
    IN p_admin_id        INT,
    IN p_delivery_status VARCHAR(20),  -- NULL = all statuses
    IN p_date_from       DATE,         -- NULL = no start filter
    IN p_date_to         DATE          -- NULL = no end filter
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    SELECT
        s.sms_id,
        s.recipient_phone,   -- admin only; contains personal data
        s.message,
        s.delivery_status,
        s.sent_at,
        -- Guard who sent the SMS
        CONCAT(g.first_name, ' ', g.last_name) AS guard_name,
        g.role              AS guard_role,
        -- Incident context
        i.incident_id,
        i.incident_type,
        i.status            AS incident_status,
        -- Resident who received the SMS
        CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
        r.house_no
    FROM  sms_logs s
    JOIN  users     g ON s.guard_id    = g.user_id
    JOIN  incidents i ON s.incident_id = i.incident_id
    JOIN  users     r ON i.user_id     = r.user_id
    WHERE (p_delivery_status IS NULL OR s.delivery_status = p_delivery_status)
      AND (p_date_from       IS NULL OR DATE(s.sent_at)  >= p_date_from)
      AND (p_date_to         IS NULL OR DATE(s.sent_at)  <= p_date_to)
    ORDER BY s.sent_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_cctv_audit_trail` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_cctv_audit_trail` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_cctv_audit_trail`(
    IN p_admin_id INT,
    IN p_feed_id  INT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Validate feed exists (may have been deleted — check
    -- audit_log directly if feed record no longer exists)
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM audit_log
        WHERE target_table = 'cctv_feeds'
          AND target_id    = p_feed_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No audit records found for this CCTV feed ID.';
    END IF;

    SELECT
        al.log_id,
        al.action,
        al.old_value,
        al.new_value,
        al.created_at  AS actioned_at,
        CONCAT(u.first_name, ' ', u.last_name) AS actioned_by,
        u.role         AS actor_role
    FROM  audit_log al
    JOIN  users u ON al.actor_id = u.user_id
    WHERE al.target_table = 'cctv_feeds'
      AND al.target_id    = p_feed_id
    ORDER BY al.created_at ASC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_cctv_by_id` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_cctv_by_id` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_cctv_by_id`(
    IN p_requesting_user_id INT,
    IN p_feed_id            INT
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Verify requester is active and verified
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Active verified account required.';
    END IF;

    -- --------------------------------------------------------
    -- Validate feed exists
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM cctv_feeds WHERE feed_id = p_feed_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CCTV feed not found.';
    END IF;

    -- --------------------------------------------------------
    -- Role-aware response
    -- --------------------------------------------------------
    SELECT
        feed_id,
        feed_name,
        location,
        latitude,
        longitude,
        feed_status,
        created_at,
        CASE
            WHEN v_requester_role IN ('guard','admin') THEN stream_url
            ELSE NULL
        END AS stream_url
    FROM cctv_feeds
    WHERE feed_id = p_feed_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_cctv_feed_status_summary` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_cctv_feed_status_summary` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_cctv_feed_status_summary`(
    IN p_admin_id INT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Result set 1: overall feed counts
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                                   AS total_feeds,
        SUM(CASE WHEN feed_status = 'active'   THEN 1 ELSE 0 END) AS active_feeds,
        SUM(CASE WHEN feed_status = 'inactive' THEN 1 ELSE 0 END) AS inactive_feeds
    FROM cctv_feeds;

    -- --------------------------------------------------------
    -- Result set 2: per-feed activity summary
    -- Shows each feed with its linked incident and panic counts.
    -- Useful for identifying cameras covering high-risk areas
    -- and cameras that have never captured a security event.
    -- stream_url excluded — summary view only.
    -- --------------------------------------------------------
    SELECT
        c.feed_id,
        c.feed_name,
        c.location,
        c.feed_status,
        c.latitude,
        c.longitude,
        -- Count of ALL incidents linked (including resolved and soft-deleted)
        COUNT(DISTINCT i.incident_id)  AS total_linked_incidents,
        -- Count of open incidents only
        SUM(CASE
            WHEN i.status = 'open' AND i.is_deleted = 0
            THEN 1 ELSE 0
        END)                           AS open_incidents,
        -- Count of ALL panic alerts linked
        COUNT(DISTINCT pa.alert_id)    AS total_linked_panics,
        -- Count of active panics only
        SUM(CASE
            WHEN pa.status = 'active'
            THEN 1 ELSE 0
        END)                           AS active_panics
    FROM      cctv_feeds c
    LEFT JOIN incidents   i  ON c.feed_id = i.cctv_feed_id
    LEFT JOIN panic_alerts pa ON c.feed_id = pa.cctv_feed_id
    GROUP BY c.feed_id, c.feed_name, c.location,
             c.feed_status, c.latitude, c.longitude
    ORDER BY total_linked_incidents DESC, total_linked_panics DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_deleted_incidents` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_deleted_incidents` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_deleted_incidents`(
    IN p_admin_id INT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    SELECT
        i.incident_id,
        i.incident_type,
        i.description,
        i.status         AS status_at_deletion,
        i.reported_at,
        i.deleted_at,
        CONCAT(r.first_name,  ' ', r.last_name) AS reported_by,
        r.house_no,
        CONCAT(d.first_name,  ' ', d.last_name) AS deleted_by_name,
        d.role           AS deleted_by_role
    FROM  incidents i
    JOIN  users r ON i.user_id    = r.user_id
    JOIN  users d ON i.deleted_by = d.user_id
    WHERE i.is_deleted = 1
    ORDER BY i.deleted_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_escalations` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_escalations` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_escalations`(
    IN p_requesting_user_id INT,
    IN p_date_from          DATE,   -- NULL = no start filter
    IN p_date_to            DATE    -- NULL = no end filter
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_requesting_user_id
          AND role                IN ('guard','admin')
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guards and admins only.';
    END IF;

    SELECT
        e.escalation_id,
        e.reference_code,
        e.station_name,
        e.escalated_at,
        -- Incident summary
        i.incident_id,
        i.incident_type,
        i.status        AS incident_status,
        i.reported_at   AS incident_reported_at,
        -- Resident: name and house only in list view
        -- Phone available in sp_get_escalation_by_id
        CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
        r.house_no,
        -- Guard who escalated
        CONCAT(u.first_name, ' ', u.last_name) AS escalated_by_name,
        u.role          AS escalated_by_role
        -- cctv_url intentionally excluded
        -- resident_phone intentionally excluded from list view
    FROM  police_escalations e
    JOIN  incidents i ON e.incident_id  = i.incident_id
    JOIN  users     r ON i.user_id      = r.user_id
    JOIN  users     u ON e.escalated_by = u.user_id
    WHERE (p_date_from IS NULL OR DATE(e.escalated_at) >= p_date_from)
      AND (p_date_to   IS NULL OR DATE(e.escalated_at) <= p_date_to)
    ORDER BY e.escalated_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_escalations_by_incident` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_escalations_by_incident` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_escalations_by_incident`(
    IN p_requesting_user_id INT,
    IN p_incident_id        INT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_requesting_user_id
          AND role                IN ('guard','admin')
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guards and admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Validate incident exists
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM incidents
        WHERE  incident_id = p_incident_id
          AND  is_deleted  = 0
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident not found.';
    END IF;

    SELECT
        e.escalation_id,
        e.reference_code,
        e.station_name,
        e.notes,
        e.escalated_at,
        CONCAT(u.first_name, ' ', u.last_name) AS escalated_by_name,
        u.role          AS escalated_by_role
        -- resident_phone intentionally excluded
        -- cctv_url intentionally excluded
    FROM  police_escalations e
    JOIN  users u ON e.escalated_by = u.user_id
    WHERE e.incident_id = p_incident_id
    ORDER BY e.escalated_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_escalation_by_id` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_escalation_by_id` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_escalation_by_id`(
    IN p_requesting_user_id INT,
    IN p_escalation_id      INT
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL OR
       v_requester_role NOT IN ('guard','admin')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guards and admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Validate escalation exists
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM police_escalations
        WHERE  escalation_id = p_escalation_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Escalation record not found.';
    END IF;

    -- --------------------------------------------------------
    -- Role-aware response
    -- --------------------------------------------------------
    SELECT
        e.escalation_id,
        e.reference_code,
        e.station_name,
        e.notes,
        e.escalated_at,

        -- Incident detail
        i.incident_id,
        i.incident_type,
        i.description   AS incident_description,
        i.latitude      AS incident_latitude,
        i.longitude     AS incident_longitude,
        i.status        AS incident_status,
        i.reported_at   AS incident_reported_at,

        -- Resident identity
        CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
        r.house_no,

        -- Resident phone: guard and admin only
        CASE
            WHEN v_requester_role IN ('guard','admin') THEN r.phone_no
            ELSE NULL
        END AS resident_phone,

        -- Escalating guard identity
        CONCAT(u.first_name, ' ', u.last_name) AS escalated_by_name,
        u.role          AS escalated_by_role,

        -- Guard phone: admin only
        CASE
            WHEN v_requester_role = 'admin' THEN u.phone_no
            ELSE NULL
        END AS escalated_by_phone,

        -- CCTV: feed name visible to all; stream_url to admin only
        c.feed_name     AS cctv_feed,
        c.location      AS cctv_location,
        CASE
            WHEN v_requester_role = 'admin' THEN c.stream_url
            ELSE NULL
        END AS cctv_url

    FROM  police_escalations e
    JOIN  incidents   i ON e.incident_id  = i.incident_id
    JOIN  users       r ON i.user_id      = r.user_id
    JOIN  users       u ON e.escalated_by = u.user_id
    LEFT JOIN cctv_feeds c ON i.cctv_feed_id = c.feed_id
    WHERE e.escalation_id = p_escalation_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_incidents_by_user` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_incidents_by_user` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_incidents_by_user`(
    IN p_requesting_user_id INT,
    IN p_target_user_id     INT
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Verify requester is active and verified
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Active verified account required.';
    END IF;

    -- --------------------------------------------------------
    -- Ownership check: residents see only their own incidents
    -- --------------------------------------------------------
    IF v_requester_role = 'resident'
       AND p_requesting_user_id <> p_target_user_id
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Residents can only view their own incident history.';
    END IF;

    -- --------------------------------------------------------
    -- Verify target user exists
    -- --------------------------------------------------------
    IF NOT EXISTS (SELECT 1 FROM users WHERE user_id = p_target_user_id) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'User not found.';
    END IF;

    SELECT
        i.incident_id,
        i.incident_type,
        i.description,
        i.latitude,
        i.longitude,
        i.status,
        i.guard_note,
        i.reported_at,
        i.updated_at,
        CONCAT(g.first_name, ' ', g.last_name) AS guard_name,
        c.feed_name  AS cctv_feed,
        c.location   AS cctv_location
        -- stream_url intentionally excluded
        -- resident_phone intentionally excluded
    FROM  incidents i
    LEFT JOIN users       g ON i.assigned_guard_id = g.user_id
    LEFT JOIN cctv_feeds  c ON i.cctv_feed_id      = c.feed_id
    WHERE i.user_id    = p_target_user_id
      AND i.is_deleted = 0
    ORDER BY i.reported_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_incident_by_id` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_incident_by_id` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_incident_by_id`(
    IN p_requesting_user_id INT,
    IN p_incident_id        INT
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;
    DECLARE v_owner_id       INT         DEFAULT NULL;

    -- --------------------------------------------------------
    -- Verify requester is active and verified
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Active verified account required.';
    END IF;

    -- --------------------------------------------------------
    -- Verify incident exists and is not soft-deleted
    -- --------------------------------------------------------
    SELECT user_id INTO v_owner_id
    FROM   incidents
    WHERE  incident_id = p_incident_id
      AND  is_deleted  = 0;

    IF v_owner_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident not found.';
    END IF;

    -- --------------------------------------------------------
    -- Ownership check: residents see only their own incidents
    -- --------------------------------------------------------
    IF v_requester_role = 'resident'
       AND v_owner_id <> p_requesting_user_id
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. You can only view your own incidents.';
    END IF;

    -- --------------------------------------------------------
    -- Role-aware response
    -- CASE expressions control which sensitive fields are
    -- returned based on the caller's role.
    -- --------------------------------------------------------
    SELECT
        i.incident_id,
        i.incident_type,
        i.description,
        i.latitude,
        i.longitude,
        i.status,
        i.guard_note,
        i.reported_at,
        i.updated_at,

        -- Resident identity
        CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
        r.house_no,

        -- Phone: visible to guard and admin only
        CASE
            WHEN v_requester_role IN ('guard','admin') THEN r.phone_no
            ELSE NULL
        END AS resident_phone,

        -- Guard identity
        CONCAT(g.first_name, ' ', g.last_name) AS guard_name,

        -- Guard phone: admin only
        CASE
            WHEN v_requester_role = 'admin' THEN g.phone_no
            ELSE NULL
        END AS guard_phone,

        -- CCTV: feed name visible to all; stream_url to admin only
        c.feed_name  AS cctv_feed,
        c.location   AS cctv_location,
        CASE
            WHEN v_requester_role = 'admin' THEN c.stream_url
            ELSE NULL
        END AS cctv_url

    FROM  incidents i
    JOIN  users       r ON i.user_id           = r.user_id
    LEFT JOIN users   g ON i.assigned_guard_id = g.user_id
    LEFT JOIN cctv_feeds c ON i.cctv_feed_id   = c.feed_id
    WHERE i.incident_id = p_incident_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_my_panics` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_my_panics` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_my_panics`(
    IN p_user_id INT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller must be a verified, active resident
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_user_id
          AND role                = 'resident'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Verified active resident account required.';
    END IF;

    SELECT
        pa.alert_id,
        pa.latitude,
        pa.longitude,
        pa.status,
        pa.triggered_at,
        pa.responded_at,
        CASE
            WHEN pa.responded_at IS NOT NULL
            THEN TIMESTAMPDIFF(MINUTE, pa.triggered_at, pa.responded_at)
            ELSE NULL
        END                                     AS response_time_mins,
        CONCAT(g.first_name, ' ', g.last_name) AS responding_guard,
        c.feed_name  AS cctv_feed,
        c.location   AS cctv_location
        -- stream_url intentionally excluded
        -- guard_phone intentionally excluded
    FROM  panic_alerts pa
    LEFT JOIN users      g ON pa.guard_id     = g.user_id
    LEFT JOIN cctv_feeds c ON pa.cctv_feed_id = c.feed_id
    WHERE pa.user_id = p_user_id
    ORDER BY pa.triggered_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_nearest_cctv` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_nearest_cctv` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_nearest_cctv`(
    IN p_requesting_user_id INT,
    IN p_latitude           DECIMAL(10,8),
    IN p_longitude          DECIMAL(11,8)
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- (This procedure is for server-side use only)
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL OR
       v_requester_role NOT IN ('guard','admin')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guards and admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_latitude IS NULL OR p_longitude IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'GPS coordinates are required.';
    END IF;

    -- --------------------------------------------------------
    -- Return nearest active feed
    -- stream_url intentionally excluded.
    -- Calling procedure uses feed_id only.
    -- --------------------------------------------------------
    SELECT
        feed_id,
        feed_name,
        location,
        latitude,
        longitude,
        feed_status,
        -- Distance in approximate degrees (for ordering only;
        -- not a metric distance — sufficient for nearest-feed
        -- selection within a small residential estate)
        SQRT(
            POW(latitude  - p_latitude,  2) +
            POW(longitude - p_longitude, 2)
        ) AS distance_approx
        -- stream_url intentionally excluded
    FROM cctv_feeds
    WHERE feed_status = 'active'
    ORDER BY distance_approx ASC
    LIMIT 1;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_overdue_subscriptions` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_overdue_subscriptions` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_overdue_subscriptions`(
    IN p_admin_id INT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Residents with no active subscription
    -- Includes both expired subscribers and those who
    -- have never paid.
    -- --------------------------------------------------------
    SELECT
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) AS resident_name,
        u.house_no,
        u.phone_no         AS resident_phone,
        u.email            AS resident_email,
        -- Latest completed payment details (NULL if never paid)
        MAX(p.subscription_end)                AS last_subscription_end,
        -- Days since expiry (positive = overdue, NULL = never paid)
        CASE
            WHEN MAX(p.subscription_end) IS NOT NULL
            THEN DATEDIFF(CURDATE(), MAX(p.subscription_end))
            ELSE NULL
        END AS days_overdue,
        -- Category for admin UI display
        CASE
            WHEN MAX(p.payment_status) IS NULL
            THEN 'never_paid'
            WHEN MAX(p.subscription_end) < CURDATE()
            THEN 'expired'
            ELSE 'active'   -- should not appear here but included for safety
        END AS overdue_category
    FROM  users u
    LEFT JOIN payments p
           ON u.user_id = p.user_id
          AND p.payment_status = 'completed'
    WHERE u.role                = 'resident'
      AND u.status              = 'active'
      AND u.verification_status = 'verified'
    GROUP BY
        u.user_id, u.first_name, u.last_name,
        u.house_no, u.phone_no, u.email
    HAVING
        -- Exclude residents with a currently active subscription
        MAX(p.subscription_end) IS NULL
        OR MAX(p.subscription_end) < CURDATE()
    ORDER BY
	    days_overdue IS NULL,
	    days_overdue DESC,
	    u.house_no ASC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_panic_by_id` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_panic_by_id` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_panic_by_id`(
    IN p_requesting_user_id INT,
    IN p_alert_id           INT
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;
    DECLARE v_alert_owner_id INT         DEFAULT NULL;

    -- --------------------------------------------------------
    -- Verify requester is active and verified
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Active verified account required.';
    END IF;

    -- --------------------------------------------------------
    -- Validate alert exists
    -- --------------------------------------------------------
    SELECT user_id INTO v_alert_owner_id
    FROM   panic_alerts
    WHERE  alert_id = p_alert_id;

    IF v_alert_owner_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Panic alert not found.';
    END IF;

    -- --------------------------------------------------------
    -- Ownership check: residents see only their own alerts
    -- --------------------------------------------------------
    IF v_requester_role = 'resident'
       AND v_alert_owner_id <> p_requesting_user_id
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. You can only view your own panic alerts.';
    END IF;

    -- --------------------------------------------------------
    -- Role-aware response
    -- --------------------------------------------------------
    SELECT
        pa.alert_id,
        pa.latitude,
        pa.longitude,
        pa.status,
        pa.triggered_at,
        pa.responded_at,
        TIMESTAMPDIFF(MINUTE, pa.triggered_at,
            COALESCE(pa.responded_at, NOW())
        )                                          AS response_time_mins,

        -- Resident identity
        CONCAT(r.first_name, ' ', r.last_name)    AS resident_name,
        r.house_no,

        -- Resident phone: guard and admin only
        CASE
            WHEN v_requester_role IN ('guard','admin') THEN r.phone_no
            ELSE NULL
        END AS resident_phone,

        -- Guard identity
        CONCAT(g.first_name, ' ', g.last_name)    AS guard_name,

        -- Guard phone: admin only
        CASE
            WHEN v_requester_role = 'admin' THEN g.phone_no
            ELSE NULL
        END AS guard_phone,

        -- CCTV: feed name to all; stream_url to guard and admin only
        c.feed_name  AS cctv_feed,
        c.location   AS cctv_location,
        CASE
            WHEN v_requester_role IN ('guard','admin') THEN c.stream_url
            ELSE NULL
        END AS cctv_url

    FROM  panic_alerts pa
    JOIN  users r ON pa.user_id  = r.user_id
    LEFT JOIN users g        ON pa.guard_id      = g.user_id
    LEFT JOIN cctv_feeds c   ON pa.cctv_feed_id  = c.feed_id
    WHERE pa.alert_id = p_alert_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_panic_history` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_panic_history` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_panic_history`(
    IN p_requesting_user_id INT,
    IN p_status             VARCHAR(20),  -- NULL = all statuses
    IN p_date_from          DATE,         -- NULL = no start filter
    IN p_date_to            DATE          -- NULL = no end filter
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_requesting_user_id
          AND role                IN ('guard','admin')
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guards and admins only.';
    END IF;

    SELECT
        pa.alert_id,
        pa.latitude,
        pa.longitude,
        pa.status,
        pa.triggered_at,
        pa.responded_at,
        -- Response time: NULL if not yet responded
        CASE
            WHEN pa.responded_at IS NOT NULL
            THEN TIMESTAMPDIFF(MINUTE, pa.triggered_at, pa.responded_at)
            ELSE NULL
        END                                        AS response_time_mins,
        CONCAT(r.first_name, ' ', r.last_name)    AS resident_name,
        r.house_no,
        r.phone_no                                 AS resident_phone,
        CONCAT(g.first_name, ' ', g.last_name)    AS guard_name,
        c.feed_name                                AS cctv_feed,
        c.location                                 AS cctv_location
        -- stream_url intentionally excluded from history view
    FROM  panic_alerts pa
    JOIN  users r ON pa.user_id = r.user_id
    LEFT JOIN users      g ON pa.guard_id     = g.user_id
    LEFT JOIN cctv_feeds c ON pa.cctv_feed_id = c.feed_id
    WHERE (p_status    IS NULL OR pa.status              = p_status)
      AND (p_date_from IS NULL OR DATE(pa.triggered_at) >= p_date_from)
      AND (p_date_to   IS NULL OR DATE(pa.triggered_at) <= p_date_to)
    ORDER BY pa.triggered_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_panic_statistics` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_panic_statistics` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_panic_statistics`(
    IN p_requesting_user_id INT,
    IN p_date_from          DATE,   -- NULL = all time
    IN p_date_to            DATE    -- NULL = all time
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_requesting_user_id
          AND role                IN ('guard','admin')
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guards and admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Result set 1: alert counts by status
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                             AS total_alerts,
        SUM(CASE WHEN status = 'active'    THEN 1 ELSE 0 END) AS active_alerts,
        SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) AS responded_alerts,
        SUM(CASE WHEN status = 'closed'    THEN 1 ELSE 0 END) AS closed_alerts,
        SUM(CASE WHEN guard_id IS NULL
                  AND status   = 'active'  THEN 1 ELSE 0 END) AS unassigned_active_alerts
    FROM panic_alerts
    WHERE (p_date_from IS NULL OR DATE(triggered_at) >= p_date_from)
      AND (p_date_to   IS NULL OR DATE(triggered_at) <= p_date_to);

    -- --------------------------------------------------------
    -- Result set 2: response time statistics (responded alerts only)
    -- --------------------------------------------------------
    SELECT
        ROUND(AVG(
            TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
        ), 1)                                              AS avg_response_time_mins,
        MIN(
            TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
        )                                                  AS fastest_response_mins,
        MAX(
            TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
        )                                                  AS slowest_response_mins,
        COUNT(*)                                           AS total_responded
    FROM panic_alerts
    WHERE responded_at IS NOT NULL
      AND (p_date_from IS NULL OR DATE(triggered_at) >= p_date_from)
      AND (p_date_to   IS NULL OR DATE(triggered_at) <= p_date_to);

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_payment_by_id` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_payment_by_id` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_payment_by_id`(
    IN p_requesting_user_id INT,
    IN p_payment_id         INT
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;
    DECLARE v_payment_owner  INT         DEFAULT NULL;

    -- --------------------------------------------------------
    -- Verify requester is active and verified
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Active verified account required.';
    END IF;

    -- --------------------------------------------------------
    -- Guards have no access to payment records
    -- --------------------------------------------------------
    IF v_requester_role = 'guard' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Payment records are not accessible to guards.';
    END IF;

    -- --------------------------------------------------------
    -- Validate payment exists
    -- --------------------------------------------------------
    SELECT user_id INTO v_payment_owner
    FROM   payments
    WHERE  payment_id = p_payment_id;

    IF v_payment_owner IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Payment record not found.';
    END IF;

    -- --------------------------------------------------------
    -- Ownership check for residents
    -- --------------------------------------------------------
    IF v_requester_role = 'resident'
       AND v_payment_owner <> p_requesting_user_id
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. You can only view your own payment records.';
    END IF;

    SELECT
        p.payment_id,
        p.amount,
        p.mpesa_ref,
        p.payment_status,
        p.subscription_start,
        p.subscription_end,
        p.paid_at,
        CASE
            WHEN p.payment_status  = 'completed'
             AND p.subscription_end >= CURDATE()
            THEN 'active'
            WHEN p.payment_status  = 'completed'
             AND p.subscription_end <  CURDATE()
            THEN 'expired'
            WHEN p.payment_status  = 'pending'
            THEN 'payment_pending'
            ELSE 'failed'
        END AS subscription_status,
        CONCAT(u.first_name, ' ', u.last_name) AS resident_name,
        u.house_no,
        -- Phone visible to admin only in single payment view
        CASE
            WHEN v_requester_role = 'admin' THEN u.phone_no
            ELSE NULL
        END AS resident_phone
    FROM  payments p
    JOIN  users u ON p.user_id = u.user_id
    WHERE p.payment_id = p_payment_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_payment_history` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_payment_history` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_payment_history`(
    IN p_requesting_user_id INT,
    IN p_target_user_id     INT
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Verify requester is active and verified
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Active verified account required.';
    END IF;

    -- --------------------------------------------------------
    -- Guards have no access to payment records
    -- --------------------------------------------------------
    IF v_requester_role = 'guard' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Payment records are not accessible to guards.';
    END IF;

    -- --------------------------------------------------------
    -- Residents can only view their own payment history
    -- --------------------------------------------------------
    IF v_requester_role = 'resident'
       AND p_requesting_user_id <> p_target_user_id
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Residents can only view their own payment history.';
    END IF;

    -- --------------------------------------------------------
    -- Validate target user exists
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users WHERE user_id = p_target_user_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'User not found.';
    END IF;

    SELECT
        p.payment_id,
        p.amount,
        p.mpesa_ref,           -- resident has right to own transaction refs
        p.payment_status,
        p.subscription_start,
        p.subscription_end,
        p.paid_at,
        CASE
            WHEN p.payment_status  = 'completed'
             AND p.subscription_end >= CURDATE()
            THEN 'active'
            WHEN p.payment_status  = 'completed'
             AND p.subscription_end <  CURDATE()
            THEN 'expired'
            WHEN p.payment_status  = 'pending'
            THEN 'payment_pending'
            ELSE 'failed'
        END AS subscription_status
    FROM payments p
    WHERE p.user_id = p_target_user_id
    ORDER BY p.paid_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_payment_statistics` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_payment_statistics` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_payment_statistics`(
    IN p_admin_id  INT,
    IN p_date_from DATE,   -- NULL = all time
    IN p_date_to   DATE    -- NULL = all time
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Result set 1: overall payment summary
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                                        AS total_payment_records,
        SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END)  AS completed_payments,
        SUM(CASE WHEN payment_status = 'pending'   THEN 1 ELSE 0 END)  AS pending_payments,
        SUM(CASE WHEN payment_status = 'failed'    THEN 1 ELSE 0 END)  AS failed_payments,
        -- Total revenue from completed payments only
        COALESCE(SUM(
            CASE WHEN payment_status = 'completed'
            THEN amount ELSE 0 END
        ), 0.00)                                                        AS total_revenue_ksh,
        -- Average payment amount (completed only)
        COALESCE(ROUND(AVG(
            CASE WHEN payment_status = 'completed'
            THEN amount ELSE NULL END
        ), 2), 0.00)                                                    AS avg_payment_ksh,
        -- Count of currently active subscriptions
        SUM(CASE
            WHEN payment_status = 'completed'
             AND subscription_end >= CURDATE()
            THEN 1 ELSE 0
        END)                                                            AS active_subscriptions,
        -- Count of expired subscriptions
        SUM(CASE
            WHEN payment_status = 'completed'
             AND subscription_end < CURDATE()
            THEN 1 ELSE 0
        END)                                                            AS expired_subscriptions
    FROM payments
    WHERE (p_date_from IS NULL OR DATE(paid_at) >= p_date_from)
      AND (p_date_to   IS NULL OR DATE(paid_at) <= p_date_to);

    -- --------------------------------------------------------
    -- Result set 2: monthly revenue breakdown
    -- Groups completed payments by year and month for
    -- trend analysis and financial reporting (Section 8)
    -- --------------------------------------------------------
    SELECT
        YEAR(paid_at)                           AS payment_year,
        MONTH(paid_at)                          AS payment_month,
        MONTHNAME(paid_at)                      AS month_name,
        COUNT(*)                                AS payment_count,
        SUM(amount)                             AS monthly_revenue_ksh,
        COUNT(DISTINCT user_id)                 AS unique_paying_residents
    FROM payments
    WHERE payment_status = 'completed'
      AND (p_date_from IS NULL OR DATE(paid_at) >= p_date_from)
      AND (p_date_to   IS NULL OR DATE(paid_at) <= p_date_to)
    GROUP BY
        YEAR(paid_at),
        MONTH(paid_at),
        MONTHNAME(paid_at)
    ORDER BY
        YEAR(paid_at)  DESC,
        MONTH(paid_at) DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_pending_residents` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_pending_residents` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_pending_residents`(
    IN p_admin_id INT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only active verified admins can view pending residents.';
    END IF;

    -- Return minimum fields required for admin review
    SELECT
        user_id,
        first_name,
        last_name,
        email,
        phone_no,
        house_no,
        created_at
    FROM users
    WHERE role                = 'resident'
      AND verification_status = 'pending'
    ORDER BY created_at ASC;  -- oldest first; process in order of registration

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_sms_by_incident` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_sms_by_incident` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_sms_by_incident`(
    IN p_requesting_user_id INT,
    IN p_incident_id        INT
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL OR
       v_requester_role NOT IN ('guard','admin')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guards and admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Validate incident exists
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM incidents
        WHERE  incident_id = p_incident_id
          AND  is_deleted  = 0
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident not found.';
    END IF;

    SELECT
        s.sms_id,
        -- recipient_phone: admin only; guards see delivery status only
        CASE
            WHEN v_requester_role = 'admin' THEN s.recipient_phone
            ELSE NULL
        END AS recipient_phone,
        s.message,
        s.delivery_status,
        s.sent_at,
        CONCAT(g.first_name, ' ', g.last_name) AS guard_name,
        i.incident_type,
        i.status AS incident_status
    FROM  sms_logs s
    JOIN  users     g ON s.guard_id    = g.user_id
    JOIN  incidents i ON s.incident_id = i.incident_id
    WHERE s.incident_id = p_incident_id
    ORDER BY s.sent_at DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_sms_statistics` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_sms_statistics` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_sms_statistics`(
    IN p_admin_id  INT,
    IN p_date_from DATE,  -- NULL = all time
    IN p_date_to   DATE   -- NULL = all time
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Result set 1: overall delivery summary
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                                           AS total_sms_logged,
        SUM(CASE WHEN delivery_status = 'sent'      THEN 1 ELSE 0 END)    AS total_sent,
        SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END)    AS total_delivered,
        SUM(CASE WHEN delivery_status = 'failed'    THEN 1 ELSE 0 END)    AS total_failed,
        -- Delivery success rate as percentage
        CASE
            WHEN COUNT(*) > 0
            THEN ROUND(
                (SUM(CASE WHEN delivery_status = 'delivered'
                     THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1
            )
            ELSE 0
        END AS delivery_success_rate_pct,
        -- Count of unique incidents that triggered SMS communication
        COUNT(DISTINCT incident_id)                                        AS incidents_with_sms,
        -- Count of unique residents who received at least one SMS
        COUNT(DISTINCT guard_id)                                           AS guards_who_sent_sms
    FROM sms_logs
    WHERE (p_date_from IS NULL OR DATE(sent_at) >= p_date_from)
      AND (p_date_to   IS NULL OR DATE(sent_at) <= p_date_to);

    -- --------------------------------------------------------
    -- Result set 2: per-guard SMS activity
    -- Identifies which guards are actively communicating
    -- with residents and which guards have high failure rates.
    -- No personal data — guard name and aggregate counts only.
    -- --------------------------------------------------------
    SELECT
        CONCAT(u.first_name, ' ', u.last_name)                    AS guard_name,
        COUNT(s.sms_id)                                           AS total_sms_sent,
        SUM(CASE WHEN s.delivery_status = 'delivered'
            THEN 1 ELSE 0 END)                                    AS delivered_count,
        SUM(CASE WHEN s.delivery_status = 'failed'
            THEN 1 ELSE 0 END)                                    AS failed_count,
        CASE
            WHEN COUNT(s.sms_id) > 0
            THEN ROUND(
                (SUM(CASE WHEN s.delivery_status = 'failed'
                     THEN 1 ELSE 0 END) / COUNT(s.sms_id)) * 100, 1
            )
            ELSE 0
        END AS failure_rate_pct,
        COUNT(DISTINCT s.incident_id)                             AS unique_incidents_handled
    FROM  sms_logs s
    JOIN  users    u ON s.guard_id = u.user_id
    WHERE (p_date_from IS NULL OR DATE(s.sent_at) >= p_date_from)
      AND (p_date_to   IS NULL OR DATE(s.sent_at) <= p_date_to)
    GROUP BY u.user_id, u.first_name, u.last_name
    ORDER BY total_sms_sent DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_user_for_login` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_user_for_login` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_user_for_login`(
    IN p_email VARCHAR(255)
)
BEGIN
    IF p_email IS NULL OR TRIM(p_email) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Email address is required.';
    END IF;

    -- Returns only what PHP needs to authenticate.
    -- PHP usage pattern:
    --   $row = $db->callProcedure('sp_get_user_for_login', [$email]);
    --   if (!$row) { return 401 invalid credentials; }
    --   if (!password_verify($plainPassword, $row['password_hash'])) { return 401; }
    --   if ($row['status'] !== 'active') { return 403 account inactive; }
    --   if ($row['verification_status'] !== 'verified') { return 403 pending; }
    --   // Build JWT: { user_id, role, full_name }
    SELECT
        user_id,
        password_hash,          -- returned ONLY for PHP's password_verify()
        role,
        status,
        verification_status,
        first_name,
        last_name
    FROM users
    WHERE email = LOWER(TRIM(p_email))
    LIMIT 1;

    -- NOTE: No audit log entry here. Logging every login attempt
    -- would rapidly inflate the audit_log table. Failed attempts
    -- are handled at the PHP/application layer (rate limiting,
    -- lockout). Successful logins can be logged in PHP after
    -- password_verify() returns true if required.

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_get_user_profile` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_get_user_profile` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_user_profile`(
    IN p_requesting_user_id INT,
    IN p_target_user_id     INT
)
BEGIN
    DECLARE v_requester_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Verify requester is active and verified
    -- --------------------------------------------------------
    SELECT role INTO v_requester_role
    FROM   users
    WHERE  user_id             = p_requesting_user_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_requester_role IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Active verified account required.';
    END IF;

    -- --------------------------------------------------------
    -- Ownership check for residents
    -- Residents may only view their own profile.
    -- Guards and admins may view any profile.
    -- --------------------------------------------------------
    IF v_requester_role = 'resident'
       AND p_requesting_user_id <> p_target_user_id
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Residents can only view their own profile.';
    END IF;

    -- --------------------------------------------------------
    -- Check target user exists
    -- --------------------------------------------------------
    IF NOT EXISTS (SELECT 1 FROM users WHERE user_id = p_target_user_id) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'User not found.';
    END IF;

    -- Return profile — no password_hash
    SELECT
        user_id,
        first_name,
        last_name,
        email,
        phone_no,
        role,
        house_no,
        status,
        verification_status,
        created_at,
        updated_at
    FROM users
    WHERE user_id = p_target_user_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_log_sms` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_log_sms` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_log_sms`(
    IN p_incident_id     INT,
    IN p_guard_id        INT,
    IN p_recipient_phone VARCHAR(20),
    IN p_message         TEXT,
    IN p_delivery_status ENUM('sent','delivered','failed')
)
BEGIN
    DECLARE v_new_sms_id INT DEFAULT NULL;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_incident_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident ID is required.';
    END IF;

    IF p_recipient_phone IS NULL OR TRIM(p_recipient_phone) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Recipient phone number is required.';
    END IF;

    IF p_message IS NULL OR TRIM(p_message) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'SMS message content is required.';
    END IF;

    IF p_delivery_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Delivery status is required.';
    END IF;

    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_guard_id
          AND role                IN ('guard','admin')
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guard or admin account required to log SMS communications.';
    END IF;

    -- --------------------------------------------------------
    -- Validate incident: must exist and not be soft-deleted
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM incidents
        WHERE  incident_id = p_incident_id
          AND  is_deleted  = 0
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident not found or has been deleted.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic insert + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        INSERT INTO sms_logs (
            incident_id,
            guard_id,
            recipient_phone,
            message,
            delivery_status,
            sent_at
        )
        VALUES (
            p_incident_id,
            p_guard_id,
            TRIM(p_recipient_phone),
            p_message,
            p_delivery_status,
            NOW()
        );

        SET v_new_sms_id = LAST_INSERT_ID();

        -- Meta-audit: record that an SMS was logged
        -- recipient_phone excluded from audit log
        -- (phone number is personal data; it exists in sms_logs
        --  which is itself the authoritative audit record)
        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_guard_id,
            'LOG_SMS',
            'sms_logs',
            v_new_sms_id,
            JSON_OBJECT(
                'incident_id',    p_incident_id,
                'delivery_status',p_delivery_status
                -- recipient_phone intentionally excluded
            )
        );

    COMMIT;

    SELECT
        v_new_sms_id      AS sms_id,
        p_delivery_status AS delivery_status,
        'SMS communication logged successfully.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_register_user` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_register_user` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_register_user`(
    IN p_first_name    VARCHAR(100),
    IN p_last_name     VARCHAR(100),
    IN p_email         VARCHAR(255),
    IN p_phone_no      VARCHAR(20),

    -- Accepts a bcrypt hash produced by PHP's password_hash().
    -- PHP usage: $hash = password_hash($password, PASSWORD_BCRYPT);
    -- Never pass a plain-text password or MD5 hash to this procedure.
    IN p_password_hash VARCHAR(255),

    IN p_house_no      VARCHAR(10)
    -- NOTE: role is intentionally NOT a parameter.
    -- All self-registrations are fixed to 'resident'.
)
BEGIN
    DECLARE v_new_user_id INT DEFAULT 0;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_first_name IS NULL OR TRIM(p_first_name) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'First name is required.';
    END IF;

    IF p_last_name IS NULL OR TRIM(p_last_name) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Last name is required.';
    END IF;

    IF p_email IS NULL OR TRIM(p_email) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Email address is required.';
    END IF;

    IF p_phone_no IS NULL OR TRIM(p_phone_no) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Phone number is required.';
    END IF;

    IF p_password_hash IS NULL OR TRIM(p_password_hash) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Password hash is required.';
    END IF;

    IF p_house_no IS NULL OR TRIM(p_house_no) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'House number is required.';
    END IF;

    -- --------------------------------------------------------
    -- Duplicate checks
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM users WHERE email = p_email
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'An account with this email address already exists.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM users WHERE phone_no = p_phone_no
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'An account with this phone number already exists.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic insert + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        INSERT INTO users (
            first_name,
            last_name,
            email,
            phone_no,
            password_hash,
            role,                  -- forced to 'resident'
            house_no,
            status,
            verification_status    -- 'pending' until admin approves
        )
        VALUES (
            TRIM(p_first_name),
            TRIM(p_last_name),
            LOWER(TRIM(p_email)),  -- normalise email to lowercase
            TRIM(p_phone_no),
            p_password_hash,
            'resident',
            UPPER(TRIM(p_house_no)),
            'active',
            'pending'
        );

        SET v_new_user_id = LAST_INSERT_ID();

        -- Audit: actor = new user (their own registration event)
        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            v_new_user_id,
            'REGISTER_USER',
            'users',
            v_new_user_id,
            JSON_OBJECT(
                'email',               LOWER(TRIM(p_email)),
                'house_no',            UPPER(TRIM(p_house_no)),
                'verification_status', 'pending'
            )
        );

    COMMIT;

    -- Return minimum fields — no password_hash, no internal IDs
    SELECT
        v_new_user_id AS user_id,
        'Registration submitted. Your account is pending admin verification.'
            AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_report_crime_trends` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_report_crime_trends` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_crime_trends`(
    IN p_admin_id  INT,
    IN p_date_from DATE,
    IN p_date_to   DATE
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    IF p_date_from IS NULL OR p_date_to IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Date range is required.';
    END IF;

    IF p_date_from > p_date_to THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Start date must be before or equal to end date.';
    END IF;

    -- --------------------------------------------------------
    -- Result set 1: incidents by hour of day
    -- Identifies peak risk periods for patrol scheduling.
    -- --------------------------------------------------------
    SELECT
        HOUR(reported_at)       AS hour_of_day,
        CASE
            WHEN HOUR(reported_at) BETWEEN  6 AND 11 THEN 'Morning (06:00-11:59)'
            WHEN HOUR(reported_at) BETWEEN 12 AND 17 THEN 'Afternoon (12:00-17:59)'
            WHEN HOUR(reported_at) BETWEEN 18 AND 21 THEN 'Evening (18:00-21:59)'
            ELSE                                           'Night (22:00-05:59)'
        END AS time_period,
        COUNT(*)                AS incident_count,
        incident_type
    FROM incidents
    WHERE DATE(reported_at) BETWEEN p_date_from AND p_date_to
      AND is_deleted = 0
    GROUP BY
        HOUR(reported_at),
        time_period,
        incident_type
    ORDER BY incident_count DESC;

    -- --------------------------------------------------------
    -- Result set 2: CCTV coverage effectiveness
    -- Compares incidents with and without CCTV coverage.
    -- A high proportion of incidents without CCTV may indicate
    -- gaps in camera coverage that require new installations.
    -- --------------------------------------------------------
    SELECT
        CASE
            WHEN cctv_feed_id IS NOT NULL THEN 'With CCTV Coverage'
            ELSE                               'Without CCTV Coverage'
        END AS cctv_coverage,
        COUNT(*)                                   AS incident_count,
        SUM(CASE WHEN status = 'resolved'
             THEN 1 ELSE 0 END)                    AS resolved_count,
        -- Resolution rate by coverage category
        ROUND(
            SUM(CASE WHEN status = 'resolved'
                THEN 1 ELSE 0 END) * 100.0
            / COUNT(*), 1
        )                                          AS resolution_rate_pct
    FROM incidents
    WHERE DATE(reported_at) BETWEEN p_date_from AND p_date_to
      AND is_deleted = 0
    GROUP BY
        CASE
            WHEN cctv_feed_id IS NOT NULL THEN 'With CCTV Coverage'
            ELSE 'Without CCTV Coverage'
        END;

    -- --------------------------------------------------------
    -- Result set 3: incident count by estate block
    -- Groups house numbers by first character (block letter).
    -- Identifies which residential blocks have highest risk.
    -- No individual house numbers returned — block level only.
    -- --------------------------------------------------------
    SELECT
        LEFT(u.house_no, 1)      AS estate_block,
        COUNT(i.incident_id)     AS incident_count,
        SUM(CASE WHEN i.status = 'resolved'
             THEN 1 ELSE 0 END)  AS resolved_count,
        COUNT(DISTINCT i.user_id)AS unique_reporting_residents
    FROM incidents i
    JOIN users u ON i.user_id = u.user_id
    WHERE DATE(i.reported_at) BETWEEN p_date_from AND p_date_to
      AND i.is_deleted = 0
    GROUP BY LEFT(u.house_no, 1)
    ORDER BY incident_count DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_report_full_security_summary` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_report_full_security_summary` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_full_security_summary`(
    IN p_admin_id  INT,
    IN p_date_from DATE,
    IN p_date_to   DATE
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    IF p_date_from IS NULL OR p_date_to IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Date range is required.';
    END IF;

    -- --------------------------------------------------------
    -- Result set 1: estate overview
    -- --------------------------------------------------------
    SELECT
        (SELECT COUNT(*) FROM users
         WHERE role = 'resident' AND status = 'active'
           AND verification_status = 'verified')   AS total_verified_residents,
        (SELECT COUNT(*) FROM users
         WHERE role = 'resident'
           AND verification_status = 'pending')    AS pending_verification,
        (SELECT COUNT(*) FROM users
         WHERE role = 'guard' AND status = 'active') AS active_guards,
        (SELECT COUNT(*) FROM cctv_feeds
         WHERE feed_status = 'active')             AS active_cctv_feeds,
        p_date_from                                AS report_period_from,
        p_date_to                                  AS report_period_to;

    -- --------------------------------------------------------
    -- Result set 2: incident overview for period
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                   AS total_incidents,
        SUM(CASE WHEN is_deleted = 0
             AND status = 'open'        THEN 1 ELSE 0 END) AS open_incidents,
        SUM(CASE WHEN is_deleted = 0
             AND status = 'under_review' THEN 1 ELSE 0 END) AS under_review,
        SUM(CASE WHEN is_deleted = 0
             AND status = 'resolved'    THEN 1 ELSE 0 END) AS resolved_incidents,
        ROUND(
            SUM(CASE WHEN is_deleted = 0
                 AND status = 'resolved'
                THEN 1 ELSE 0 END) * 100.0
            / NULLIF(SUM(CASE WHEN is_deleted = 0
                THEN 1 ELSE 0 END), 0), 1
        )                                          AS resolution_rate_pct
    FROM incidents
    WHERE DATE(reported_at) BETWEEN p_date_from AND p_date_to;

    -- --------------------------------------------------------
    -- Result set 3: panic alert performance for period
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                   AS total_panic_alerts,
        SUM(CASE WHEN status = 'active'
             THEN 1 ELSE 0 END)                   AS currently_active,
        SUM(CASE WHEN status = 'closed'
             THEN 1 ELSE 0 END)                   AS closed_alerts,
        ROUND(AVG(
            CASE WHEN responded_at IS NOT NULL
            THEN TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
            ELSE NULL END
        ), 1)                                      AS avg_response_mins
    FROM panic_alerts
    WHERE DATE(triggered_at) BETWEEN p_date_from AND p_date_to;

    -- --------------------------------------------------------
    -- Result set 4: payment collection for period
    -- --------------------------------------------------------
    SELECT
        SUM(CASE WHEN payment_status = 'completed'
             THEN 1 ELSE 0 END)                   AS completed_payments,
        COALESCE(SUM(
            CASE WHEN payment_status = 'completed'
            THEN amount ELSE 0 END
        ), 0.00)                                   AS total_revenue_ksh,
        (SELECT COUNT(*) FROM users
         WHERE role = 'resident' AND status = 'active'
           AND verification_status = 'verified'
           AND user_id NOT IN (
               SELECT DISTINCT user_id FROM payments
               WHERE payment_status = 'completed'
                 AND subscription_end >= CURDATE()
           ))                                      AS residents_without_active_sub
    FROM payments
    WHERE DATE(paid_at) BETWEEN p_date_from AND p_date_to;

    -- --------------------------------------------------------
    -- Result set 5: police escalation summary for period
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                   AS total_escalations,
        COUNT(DISTINCT incident_id)                AS unique_incidents_escalated,
        COUNT(DISTINCT station_name)               AS police_stations_contacted
    FROM police_escalations
    WHERE DATE(escalated_at) BETWEEN p_date_from AND p_date_to;

    -- --------------------------------------------------------
    -- Result set 6: SMS communication summary for period
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                      AS total_sms_logged,
        SUM(CASE WHEN delivery_status = 'delivered'
             THEN 1 ELSE 0 END)                      AS delivered,
        SUM(CASE WHEN delivery_status = 'failed'
             THEN 1 ELSE 0 END)                      AS failed,
        CASE
            WHEN COUNT(*) > 0
            THEN ROUND(
                SUM(CASE WHEN delivery_status = 'delivered'
                    THEN 1 ELSE 0 END) * 100.0
                / COUNT(*), 1
            )
            ELSE 0
        END                                          AS delivery_success_rate_pct
    FROM sms_logs
    WHERE DATE(sent_at) BETWEEN p_date_from AND p_date_to;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_report_guard_performance` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_report_guard_performance` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_guard_performance`(
    IN p_admin_id  INT,
    IN p_date_from DATE,
    IN p_date_to   DATE
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    IF p_date_from IS NULL OR p_date_to IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Date range is required.';
    END IF;

    SELECT
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) AS guard_name,
        u.status                               AS guard_status,
        -- Incident response metrics
        COUNT(DISTINCT i.incident_id)          AS incidents_handled,
        SUM(CASE WHEN i.status = 'resolved'
             THEN 1 ELSE 0 END)               AS incidents_resolved,
        -- Panic alert response metrics
        COUNT(DISTINCT pa.alert_id)            AS panics_responded,
        ROUND(AVG(
            CASE WHEN pa.responded_at IS NOT NULL
            THEN TIMESTAMPDIFF(MINUTE, pa.triggered_at, pa.responded_at)
            ELSE NULL END
        ), 1)                                  AS avg_panic_response_mins,
        -- SMS communication metrics
        COUNT(DISTINCT s.sms_id)               AS sms_sent,
        SUM(CASE WHEN s.delivery_status = 'delivered'
             THEN 1 ELSE 0 END)               AS sms_delivered,
        SUM(CASE WHEN s.delivery_status = 'failed'
             THEN 1 ELSE 0 END)               AS sms_failed,
        -- Police escalations initiated
        COUNT(DISTINCT e.escalation_id)        AS police_escalations,
        -- Overall activity score (weighted composite)
        (COUNT(DISTINCT i.incident_id) * 2) +
        (COUNT(DISTINCT pa.alert_id)   * 3) +
        (COUNT(DISTINCT s.sms_id)      * 1)   AS activity_score
    FROM users u
    LEFT JOIN incidents        i  ON u.user_id = i.assigned_guard_id
                                  AND DATE(i.updated_at) BETWEEN p_date_from
                                  AND p_date_to
    LEFT JOIN panic_alerts     pa ON u.user_id = pa.guard_id
                                  AND DATE(pa.triggered_at) BETWEEN p_date_from
                                  AND p_date_to
    LEFT JOIN sms_logs         s  ON u.user_id = s.guard_id
                                  AND DATE(s.sent_at) BETWEEN p_date_from
                                  AND p_date_to
    LEFT JOIN police_escalations e ON u.user_id = e.escalated_by
                                   AND DATE(e.escalated_at) BETWEEN p_date_from
                                   AND p_date_to
    WHERE u.role   = 'guard'
      AND u.status = 'active'
    GROUP BY u.user_id, u.first_name, u.last_name, u.status
    ORDER BY activity_score DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_report_monthly_incidents` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_report_monthly_incidents` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_monthly_incidents`(
    IN p_admin_id  INT,
    IN p_date_from DATE,
    IN p_date_to   DATE
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    -- --------------------------------------------------------
    -- Date range validation
    -- --------------------------------------------------------
    IF p_date_from IS NULL OR p_date_to IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Date range (p_date_from and p_date_to) is required for report generation.';
    END IF;

    IF p_date_from > p_date_to THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Start date must be before or equal to end date.';
    END IF;

    -- --------------------------------------------------------
    -- Result set 1: monthly incident volume and resolution
    -- --------------------------------------------------------
    SELECT
        YEAR(reported_at)                            AS report_year,
        MONTH(reported_at)                           AS report_month,
        MONTHNAME(reported_at)                       AS month_name,
        COUNT(*)                                     AS total_incidents,
        SUM(CASE WHEN is_deleted = 0
             AND status = 'open'       THEN 1 ELSE 0 END) AS open_incidents,
        SUM(CASE WHEN is_deleted = 0
             AND status = 'under_review'THEN 1 ELSE 0 END) AS under_review,
        SUM(CASE WHEN is_deleted = 0
             AND status = 'resolved'   THEN 1 ELSE 0 END) AS resolved_incidents,
        SUM(CASE WHEN is_deleted = 1   THEN 1 ELSE 0 END) AS deleted_incidents,
        -- Resolution rate as percentage of non-deleted incidents
        CASE
            WHEN SUM(CASE WHEN is_deleted = 0 THEN 1 ELSE 0 END) > 0
            THEN ROUND(
                (SUM(CASE WHEN is_deleted = 0 AND status = 'resolved'
                     THEN 1 ELSE 0 END) /
                 SUM(CASE WHEN is_deleted = 0
                     THEN 1 ELSE 0 END)) * 100, 1
            )
            ELSE 0
        END AS resolution_rate_pct,
        -- Count of incidents that were escalated to police
        SUM(CASE
            WHEN EXISTS (
                SELECT 1 FROM police_escalations pe
                WHERE pe.incident_id = incidents.incident_id
            ) THEN 1 ELSE 0
        END) AS escalated_to_police
    FROM incidents
    WHERE DATE(reported_at) BETWEEN p_date_from AND p_date_to
    GROUP BY
        YEAR(reported_at),
        MONTH(reported_at),
        MONTHNAME(reported_at)
    ORDER BY
        YEAR(reported_at)  ASC,
        MONTH(reported_at) ASC;

    -- --------------------------------------------------------
    -- Result set 2: incident type breakdown for the full period
    -- Shows which types of incidents are most common overall.
    -- --------------------------------------------------------
    SELECT
        incident_type,
        COUNT(*)                                      AS total_count,
        SUM(CASE WHEN status = 'resolved'
             AND is_deleted = 0 THEN 1 ELSE 0 END)   AS resolved_count,
        ROUND(COUNT(*) * 100.0 /
            (SELECT COUNT(*) FROM incidents
             WHERE DATE(reported_at) BETWEEN p_date_from AND p_date_to
               AND is_deleted = 0), 1
        )                                             AS pct_of_total
    FROM incidents
    WHERE DATE(reported_at) BETWEEN p_date_from AND p_date_to
      AND is_deleted = 0
    GROUP BY incident_type
    ORDER BY total_count DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_report_panic_statistics` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_report_panic_statistics` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_panic_statistics`(
    IN p_admin_id      INT,
    IN p_date_from     DATE,
    IN p_date_to       DATE,
    IN p_response_target_mins INT  -- target response time in minutes (e.g. 15)
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    IF p_date_from IS NULL OR p_date_to IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Date range is required.';
    END IF;

    -- Default target response time to 15 minutes if not specified
    IF p_response_target_mins IS NULL OR p_response_target_mins <= 0 THEN
        SET p_response_target_mins = 15;
    END IF;

    -- --------------------------------------------------------
    -- Result set 1: overall panic performance summary
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                         AS total_panics,
        SUM(CASE WHEN status = 'active'    THEN 1 ELSE 0 END) AS currently_active,
        SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) AS responded,
        SUM(CASE WHEN status = 'closed'    THEN 1 ELSE 0 END) AS closed,
        -- Response time statistics (responded alerts only)
        ROUND(AVG(
            CASE WHEN responded_at IS NOT NULL
            THEN TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
            ELSE NULL END
        ), 1)                                           AS avg_response_mins,
        MIN(
            CASE WHEN responded_at IS NOT NULL
            THEN TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
            ELSE NULL END
        )                                               AS fastest_response_mins,
        MAX(
            CASE WHEN responded_at IS NOT NULL
            THEN TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
            ELSE NULL END
        )                                               AS slowest_response_mins,
        -- Alerts responded within target time
        SUM(CASE
            WHEN responded_at IS NOT NULL
             AND TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
                 <= p_response_target_mins
            THEN 1 ELSE 0
        END)                                            AS within_target_count,
        -- Target compliance rate
        CASE
            WHEN SUM(CASE WHEN responded_at IS NOT NULL
                     THEN 1 ELSE 0 END) > 0
            THEN ROUND(
                SUM(CASE
                    WHEN responded_at IS NOT NULL
                     AND TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
                         <= p_response_target_mins
                    THEN 1 ELSE 0 END) * 100.0 /
                SUM(CASE WHEN responded_at IS NOT NULL
                    THEN 1 ELSE 0 END), 1
            )
            ELSE 0
        END                                             AS within_target_pct,
        p_response_target_mins                          AS target_minutes
    FROM panic_alerts
    WHERE DATE(triggered_at) BETWEEN p_date_from AND p_date_to;

    -- --------------------------------------------------------
    -- Result set 2: monthly panic volume trend
    -- --------------------------------------------------------
    SELECT
        YEAR(triggered_at)                  AS report_year,
        MONTH(triggered_at)                 AS report_month,
        MONTHNAME(triggered_at)             AS month_name,
        COUNT(*)                            AS total_panics,
        SUM(CASE WHEN status = 'closed'
             THEN 1 ELSE 0 END)             AS resolved_panics,
        ROUND(AVG(
            CASE WHEN responded_at IS NOT NULL
            THEN TIMESTAMPDIFF(MINUTE, triggered_at, responded_at)
            ELSE NULL END
        ), 1)                               AS avg_response_mins
    FROM panic_alerts
    WHERE DATE(triggered_at) BETWEEN p_date_from AND p_date_to
    GROUP BY
        YEAR(triggered_at),
        MONTH(triggered_at),
        MONTHNAME(triggered_at)
    ORDER BY
        YEAR(triggered_at)  ASC,
        MONTH(triggered_at) ASC;

    -- --------------------------------------------------------
    -- Result set 3: guard response performance
    -- Names included — operational accountability for guards.
    -- No resident data, no GPS.
    -- --------------------------------------------------------
    SELECT
        CONCAT(u.first_name, ' ', u.last_name) AS guard_name,
        COUNT(pa.alert_id)                     AS total_responses,
        ROUND(AVG(
            TIMESTAMPDIFF(MINUTE, pa.triggered_at, pa.responded_at)
        ), 1)                                  AS avg_response_mins,
        MIN(TIMESTAMPDIFF(MINUTE,
            pa.triggered_at, pa.responded_at)) AS fastest_response_mins,
        SUM(CASE
            WHEN TIMESTAMPDIFF(MINUTE, pa.triggered_at, pa.responded_at)
                 <= p_response_target_mins
            THEN 1 ELSE 0 END)                 AS within_target,
        ROUND(
            SUM(CASE
                WHEN TIMESTAMPDIFF(MINUTE, pa.triggered_at, pa.responded_at)
                     <= p_response_target_mins
                THEN 1 ELSE 0 END) * 100.0
            / COUNT(pa.alert_id), 1
        )                                      AS target_compliance_pct
    FROM panic_alerts pa
    JOIN users u ON pa.guard_id = u.user_id
    WHERE pa.responded_at IS NOT NULL
      AND DATE(pa.triggered_at) BETWEEN p_date_from AND p_date_to
    GROUP BY u.user_id, u.first_name, u.last_name
    ORDER BY avg_response_mins ASC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_report_payment_summary` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_report_payment_summary` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_payment_summary`(
    IN p_admin_id  INT,
    IN p_date_from DATE,
    IN p_date_to   DATE
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    IF p_date_from IS NULL OR p_date_to IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Date range is required.';
    END IF;

    -- --------------------------------------------------------
    -- Result set 1: revenue summary for the period
    -- --------------------------------------------------------
    SELECT
        COUNT(*)                                      AS total_payment_records,
        SUM(CASE WHEN payment_status = 'completed'
             THEN 1 ELSE 0 END)                      AS completed_payments,
        SUM(CASE WHEN payment_status = 'pending'
             THEN 1 ELSE 0 END)                      AS pending_payments,
        SUM(CASE WHEN payment_status = 'failed'
             THEN 1 ELSE 0 END)                      AS failed_payments,
        COALESCE(SUM(
            CASE WHEN payment_status = 'completed'
            THEN amount ELSE 0 END
        ), 0.00)                                     AS total_revenue_ksh,
        -- Expected revenue: all verified active residents * amount
        (SELECT COUNT(*) FROM users
         WHERE role                = 'resident'
           AND status              = 'active'
           AND verification_status = 'verified') * 500.00
                                                     AS expected_revenue_ksh,
        -- Collection rate
        CASE
            WHEN (SELECT COUNT(*) FROM users
                  WHERE role = 'resident'
                    AND status = 'active'
                    AND verification_status = 'verified') > 0
            THEN ROUND(
                COALESCE(SUM(
                    CASE WHEN payment_status = 'completed'
                    THEN 1 ELSE 0 END
                ), 0) * 100.0 /
                (SELECT COUNT(*) FROM users
                 WHERE role = 'resident'
                   AND status = 'active'
                   AND verification_status = 'verified'), 1
            )
            ELSE 0
        END                                          AS collection_rate_pct,
        COUNT(DISTINCT user_id)                      AS unique_paying_residents
    FROM payments
    WHERE DATE(paid_at) BETWEEN p_date_from AND p_date_to;

    -- --------------------------------------------------------
    -- Result set 2: monthly revenue breakdown
    -- --------------------------------------------------------
    SELECT
        YEAR(paid_at)                                AS report_year,
        MONTH(paid_at)                               AS report_month,
        MONTHNAME(paid_at)                           AS month_name,
        COUNT(*)                                     AS payment_count,
        SUM(CASE WHEN payment_status = 'completed'
             THEN amount ELSE 0 END)                 AS monthly_revenue_ksh,
        COUNT(DISTINCT user_id)                      AS unique_payers
    FROM payments
    WHERE DATE(paid_at) BETWEEN p_date_from AND p_date_to
      AND payment_status = 'completed'
    GROUP BY
        YEAR(paid_at),
        MONTH(paid_at),
        MONTHNAME(paid_at)
    ORDER BY
        YEAR(paid_at)  ASC,
        MONTH(paid_at) ASC;

    -- --------------------------------------------------------
    -- Result set 3: overdue residents (for follow-up action)
    -- Resident name and house number included — needed for
    -- admin follow-up. Phone excluded — use
    -- sp_get_overdue_subscriptions for contact details.
    -- --------------------------------------------------------
    SELECT
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) AS resident_name,
        u.house_no,
        MAX(p.subscription_end)                AS last_subscription_end,
        CASE
            WHEN MAX(p.subscription_end) IS NULL
            THEN NULL
            ELSE DATEDIFF(CURDATE(), MAX(p.subscription_end))
        END                                    AS days_overdue,
        CASE
            WHEN MAX(p.payment_status) IS NULL THEN 'never_paid'
            ELSE 'expired'
        END                                    AS overdue_category
        -- phone_no excluded — use sp_get_overdue_subscriptions
    FROM users u
    LEFT JOIN payments p ON u.user_id = p.user_id
                         AND p.payment_status = 'completed'
    WHERE u.role                = 'resident'
      AND u.status              = 'active'
      AND u.verification_status = 'verified'
    GROUP BY u.user_id, u.first_name, u.last_name, u.house_no
    HAVING MAX(p.subscription_end) IS NULL
        OR MAX(p.subscription_end) < CURDATE()
    ORDER BY days_overdue DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_report_resident_activity` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_report_resident_activity` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_resident_activity`(
    IN p_admin_id  INT,
    IN p_date_from DATE,
    IN p_date_to   DATE
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    IF p_date_from IS NULL OR p_date_to IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Date range is required.';
    END IF;

    SELECT
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) AS resident_name,
        u.house_no,
        u.verification_status,
        -- Incident activity
        COUNT(DISTINCT i.incident_id)          AS total_incidents_reported,
        SUM(CASE WHEN i.status = 'resolved'
             AND i.is_deleted = 0
             THEN 1 ELSE 0 END)               AS incidents_resolved,
        -- Panic alert activity
        COUNT(DISTINCT pa.alert_id)            AS total_panic_alerts,
        SUM(CASE WHEN pa.status = 'closed'
             THEN 1 ELSE 0 END)               AS panics_resolved,
        -- Subscription status
        CASE
            WHEN MAX(p.subscription_end) >= CURDATE()
             AND MAX(p.payment_status)   = 'completed'
            THEN 'active'
            WHEN MAX(p.payment_status) IS NULL
            THEN 'never_paid'
            ELSE 'expired'
        END                                   AS subscription_status,
        MAX(p.subscription_end)               AS subscription_expires
        -- phone_no intentionally excluded (data minimization)
    FROM users u
    LEFT JOIN incidents   i  ON u.user_id = i.user_id
                             AND DATE(i.reported_at) BETWEEN p_date_from
                             AND p_date_to
    LEFT JOIN panic_alerts pa ON u.user_id = pa.user_id
                             AND DATE(pa.triggered_at) BETWEEN p_date_from
                             AND p_date_to
    LEFT JOIN payments    p  ON u.user_id = p.user_id
                             AND p.payment_status = 'completed'
    WHERE u.role                = 'resident'
      AND u.status              = 'active'
      AND u.verification_status = 'verified'
    GROUP BY
        u.user_id,
        u.first_name,
        u.last_name,
        u.house_no,
        u.verification_status
    ORDER BY
        total_incidents_reported DESC,
        total_panic_alerts       DESC;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_respond_to_panic` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_respond_to_panic` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_respond_to_panic`(
    IN p_alert_id INT,
    IN p_guard_id INT
)
BEGIN
    DECLARE v_alert_status VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_guard_id
          AND role                IN ('guard','admin')
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guard or admin account required.';
    END IF;

    -- --------------------------------------------------------
    -- Validate alert exists
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM panic_alerts WHERE alert_id = p_alert_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Panic alert not found.';
    END IF;

    -- --------------------------------------------------------
    -- Transaction with row-level lock to prevent concurrent
    -- double-response by two guards simultaneously
    -- --------------------------------------------------------
    START TRANSACTION;

        -- Lock the row before reading status.
        -- A second guard calling this concurrently will block
        -- here until the first guard's transaction commits.
        SELECT status
        INTO   v_alert_status
        FROM   panic_alerts
        WHERE  alert_id = p_alert_id
        FOR UPDATE;

        -- After acquiring lock, verify alert is still active
        IF v_alert_status <> 'active' THEN
            ROLLBACK;
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'This panic alert has already been responded to or closed.';
        END IF;

        UPDATE panic_alerts
        SET status       = 'responded',
            guard_id     = p_guard_id,
            responded_at = NOW()
        WHERE alert_id = p_alert_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            p_guard_id,
            'RESPOND_TO_PANIC',
            'panic_alerts',
            p_alert_id,
            JSON_OBJECT('status', 'active'),
            JSON_OBJECT(
                'status',       'responded',
                'guard_id',     p_guard_id,
                'responded_at', NOW()
            )
        );

    COMMIT;

    -- Return response confirmation
    SELECT
        pa.alert_id,
        pa.status,
        pa.responded_at,
        pa.latitude,
        pa.longitude,
        CONCAT(u.first_name, ' ', u.last_name) AS resident_name,
        u.house_no,
        u.phone_no AS resident_phone,  -- guard needs this to contact resident
        CONCAT(g.first_name, ' ', g.last_name) AS responding_guard,
        c.feed_name  AS cctv_feed,
        c.stream_url AS cctv_url,      -- guard needs stream URL on response
        c.location   AS cctv_location,
        'You have been assigned to this panic alert.' AS message
    FROM  panic_alerts pa
    JOIN  users u ON pa.user_id  = u.user_id
    JOIN  users g ON pa.guard_id = g.user_id
    LEFT JOIN cctv_feeds c ON pa.cctv_feed_id = c.feed_id
    WHERE pa.alert_id = p_alert_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_restore_incident` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_restore_incident` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_restore_incident`(
    IN p_admin_id    INT,
    IN p_incident_id INT,
    IN p_reason      TEXT
)
BEGIN
    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Admins only.';
    END IF;

    IF p_reason IS NULL OR TRIM(p_reason) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A documented reason is required when restoring an incident.';
    END IF;

    -- --------------------------------------------------------
    -- Validate: incident must exist and be soft-deleted
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM incidents
        WHERE incident_id = p_incident_id
          AND is_deleted  = 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident not found or is not currently deleted.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic restore + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        UPDATE incidents
        SET is_deleted = 0,
            deleted_by = NULL,
            deleted_at = NULL,
            updated_at = NOW()
        WHERE incident_id = p_incident_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            p_admin_id,
            'RESTORE_INCIDENT',
            'incidents',
            p_incident_id,
            JSON_OBJECT('is_deleted', 1),
            JSON_OBJECT(
                'is_deleted', 0,
                'reason',     p_reason
            )
        );

    COMMIT;

    SELECT
        p_incident_id AS incident_id,
        'Incident successfully restored to active view.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_toggle_cctv_feed_status` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_toggle_cctv_feed_status` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_toggle_cctv_feed_status`(
    IN p_admin_id INT,
    IN p_feed_id  INT,
    IN p_reason   TEXT    -- admin documents why they are toggling
)
BEGIN
    DECLARE v_old_status  VARCHAR(20) DEFAULT NULL;
    DECLARE v_new_status  VARCHAR(20) DEFAULT NULL;
    DECLARE v_linked_panics INT       DEFAULT 0;

    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only admins can toggle CCTV feed status.';
    END IF;

    -- --------------------------------------------------------
    -- Reason is mandatory
    -- --------------------------------------------------------
    IF p_reason IS NULL OR TRIM(p_reason) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A reason is required when toggling CCTV feed status.';
    END IF;

    -- --------------------------------------------------------
    -- Validate feed exists
    -- --------------------------------------------------------
    SELECT feed_status INTO v_old_status
    FROM   cctv_feeds
    WHERE  feed_id = p_feed_id;

    IF v_old_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CCTV feed not found.';
    END IF;

    -- --------------------------------------------------------
    -- Determine new status
    -- --------------------------------------------------------
    SET v_new_status = CASE
        WHEN v_old_status = 'active'   THEN 'inactive'
        WHEN v_old_status = 'inactive' THEN 'active'
    END;

    -- --------------------------------------------------------
    -- Operational impact check:
    -- Count active panic alerts currently linked to this feed.
    -- This does NOT block the deactivation — the admin has
    -- authority — but the response warns them of the impact.
    -- --------------------------------------------------------
    SELECT COUNT(*) INTO v_linked_panics
    FROM   panic_alerts
    WHERE  cctv_feed_id = p_feed_id
      AND  status       = 'active';

    -- --------------------------------------------------------
    -- Atomic toggle + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        UPDATE cctv_feeds
        SET feed_status = v_new_status
        WHERE feed_id = p_feed_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            p_admin_id,
            'TOGGLE_CCTV_STATUS',
            'cctv_feeds',
            p_feed_id,
            JSON_OBJECT('feed_status', v_old_status),
            JSON_OBJECT(
                'feed_status', v_new_status,
                'reason',      p_reason
            )
        );

    COMMIT;

    SELECT
        p_feed_id       AS feed_id,
        v_old_status    AS previous_status,
        v_new_status    AS new_status,
        v_linked_panics AS active_panic_alerts_affected,
        CASE
            WHEN v_linked_panics > 0
            THEN CONCAT(
                'Warning: ', v_linked_panics,
                ' active panic alert(s) are currently linked to this feed. ',
                'Guards have been notified of the status change.'
            )
            ELSE 'CCTV feed status updated successfully.'
        END AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_trigger_panic` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_trigger_panic` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_trigger_panic`(
    IN p_user_id   INT,
    IN p_latitude  DECIMAL(10,8),
    IN p_longitude DECIMAL(11,8)
)
BEGIN
    DECLARE v_feed_id  INT DEFAULT NULL;
    DECLARE v_alert_id INT DEFAULT NULL;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'User ID is required.';
    END IF;

    IF p_latitude IS NULL OR p_longitude IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'GPS coordinates are required to trigger a panic alert.';
    END IF;

    -- --------------------------------------------------------
    -- Caller authorization:
    -- Must be a verified, active resident
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_user_id
          AND role                = 'resident'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. A verified active resident account is required.';
    END IF;

    -- --------------------------------------------------------
    -- CRITICAL SAFETY CHECK: Duplicate active panic guard
    -- A resident with an existing active alert cannot trigger
    -- another until the first is closed. This prevents:
    --   (a) Dashboard flooding from a stuck or malicious client
    --   (b) Guards wasting time on duplicate alerts for one event
    --   (c) Real new emergencies being buried under duplicates
    -- The resident receives a clear message that help is already
    -- on the way — not a silent failure.
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM panic_alerts
        WHERE user_id = p_user_id
          AND status  = 'active'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'You already have an active panic alert. Help is on the way. Please wait for a guard to respond before triggering another alert.';
    END IF;

    -- --------------------------------------------------------
    -- Auto-assign nearest active CCTV feed
    -- NULL result is safe — cctv_feed_id is nullable.
    -- The alert MUST be created regardless of CCTV availability.
    -- --------------------------------------------------------
    SELECT feed_id
    INTO   v_feed_id
    FROM   cctv_feeds
    WHERE  feed_status = 'active'
    ORDER BY (
        POW(latitude  - p_latitude,  2) +
        POW(longitude - p_longitude, 2)
    ) ASC
    LIMIT 1;

    -- --------------------------------------------------------
    -- Atomic insert + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        INSERT INTO panic_alerts (
            user_id,
            cctv_feed_id,
            latitude,
            longitude,
            status,
            triggered_at
        )
        VALUES (
            p_user_id,
            v_feed_id,      -- NULL if no active CCTV; alert still created
            p_latitude,
            p_longitude,
            'active',
            NOW()
        );

        SET v_alert_id = LAST_INSERT_ID();

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_user_id,
            'TRIGGER_PANIC',
            'panic_alerts',
            v_alert_id,
            JSON_OBJECT(
                'latitude',    p_latitude,
                'longitude',   p_longitude,
                'cctv_feed_id',v_feed_id,
                'status',      'active'
            )
        );

    COMMIT;

    -- --------------------------------------------------------
    -- Resident-safe confirmation response
    -- stream_url intentionally excluded.
    -- resident_phone intentionally excluded.
    -- GPS coordinates included — resident already knows their
    -- own location and this confirms the system received it.
    -- --------------------------------------------------------
    SELECT
        pa.alert_id,
        pa.latitude,
        pa.longitude,
        pa.status,
        pa.triggered_at,
        CONCAT(u.first_name, ' ', u.last_name) AS resident_name,
        u.house_no,
        c.feed_name  AS cctv_feed,     -- name only, not stream_url
        c.location   AS cctv_location,
        'Panic alert triggered successfully. A guard has been notified and is on the way.' AS message
    FROM  panic_alerts pa
    JOIN  users u ON pa.user_id = u.user_id
    LEFT JOIN cctv_feeds c ON pa.cctv_feed_id = c.feed_id
    WHERE pa.alert_id = v_alert_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_update_cctv_feed` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_update_cctv_feed` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_cctv_feed`(
    IN p_admin_id   INT,
    IN p_feed_id    INT,
    IN p_feed_name  VARCHAR(100),
    IN p_location   VARCHAR(100),
    IN p_stream_url VARCHAR(255),
    IN p_latitude   DECIMAL(10,8),
    IN p_longitude  DECIMAL(11,8)
)
BEGIN
    DECLARE v_old_name     VARCHAR(100) DEFAULT NULL;
    DECLARE v_old_location VARCHAR(100) DEFAULT NULL;
    DECLARE v_old_status   VARCHAR(20)  DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only admins can update CCTV feeds.';
    END IF;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_feed_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Feed ID is required.';
    END IF;

    IF p_feed_name IS NULL OR TRIM(p_feed_name) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Feed name is required.';
    END IF;

    IF p_stream_url IS NULL OR TRIM(p_stream_url) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Stream URL is required.';
    END IF;

    IF p_latitude IS NULL OR p_longitude IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'GPS coordinates are required.';
    END IF;

    -- --------------------------------------------------------
    -- Validate feed exists; capture old values for audit log
    -- --------------------------------------------------------
    SELECT feed_name, location, feed_status
    INTO   v_old_name, v_old_location, v_old_status
    FROM   cctv_feeds
    WHERE  feed_id = p_feed_id;

    IF v_old_name IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CCTV feed not found.';
    END IF;

    -- --------------------------------------------------------
    -- Duplicate checks (exclude current feed from check)
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM cctv_feeds
        WHERE feed_name = TRIM(p_feed_name)
          AND feed_id  <> p_feed_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Another CCTV feed with this name already exists.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM cctv_feeds
        WHERE stream_url = TRIM(p_stream_url)
          AND feed_id   <> p_feed_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Another CCTV feed with this stream URL already exists.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic update + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        UPDATE cctv_feeds
        SET feed_name  = TRIM(p_feed_name),
            location   = TRIM(p_location),
            stream_url = TRIM(p_stream_url),
            latitude   = p_latitude,
            longitude  = p_longitude
        WHERE feed_id = p_feed_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            p_admin_id,
            'UPDATE_CCTV_FEED',
            'cctv_feeds',
            p_feed_id,
            JSON_OBJECT(
                'feed_name', v_old_name,
                'location',  v_old_location
                -- stream_url excluded from audit log
            ),
            JSON_OBJECT(
                'feed_name', TRIM(p_feed_name),
                'location',  TRIM(p_location),
                'latitude',  p_latitude,
                'longitude', p_longitude
            )
        );

    COMMIT;

    SELECT
        p_feed_id       AS feed_id,
        p_feed_name     AS feed_name,
        p_location      AS location,
        v_old_status    AS feed_status,
        'CCTV feed updated successfully.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_update_escalation_status` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_update_escalation_status` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_escalation_status`(
    IN p_admin_id      INT,
    IN p_escalation_id INT,
    IN p_new_status    ENUM('acknowledged','dispatched','closed','no_action'),
    IN p_update_notes  TEXT
)
BEGIN
    DECLARE v_incident_id    INT         DEFAULT NULL;
    DECLARE v_reference_code VARCHAR(50) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: admin only
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only admins can update escalation status.';
    END IF;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_update_notes IS NULL OR TRIM(p_update_notes) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Update notes are required when changing escalation status.';
    END IF;

    -- --------------------------------------------------------
    -- Validate escalation exists
    -- --------------------------------------------------------
    SELECT incident_id, reference_code
    INTO   v_incident_id, v_reference_code
    FROM   police_escalations
    WHERE  escalation_id = p_escalation_id;

    IF v_incident_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Escalation record not found.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic audit log + incident status update
    -- --------------------------------------------------------
    START TRANSACTION;

        -- Record escalation status change in audit log
        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, new_value
        )
        VALUES (
            p_admin_id,
            CONCAT('ESCALATION_STATUS_', UPPER(p_new_status)),
            'police_escalations',
            p_escalation_id,
            JSON_OBJECT(
                'new_status',    p_new_status,
                'reference_code',v_reference_code,
                'update_notes',  p_update_notes
            )
        );

        -- If escalation is closed or no_action, update the
        -- linked incident status to 'resolved'
        IF p_new_status IN ('closed','no_action') THEN
            UPDATE incidents
            SET status     = 'resolved',
                updated_at = NOW()
            WHERE incident_id  = v_incident_id
              AND status       <> 'resolved';

            -- Record incident resolution in audit log
            INSERT INTO audit_log (
                actor_id, action, target_table, target_id, new_value
            )
            VALUES (
                p_admin_id,
                'RESOLVE_INCIDENT_VIA_ESCALATION',
                'incidents',
                v_incident_id,
                JSON_OBJECT(
                    'status',          'resolved',
                    'escalation_id',   p_escalation_id,
                    'escalation_status', p_new_status
                )
            );
        END IF;

    COMMIT;

    SELECT
        p_escalation_id  AS escalation_id,
        v_reference_code AS reference_code,
        p_new_status     AS escalation_status,
        p_update_notes   AS update_notes,
        'Escalation status updated successfully.' AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_update_incident_status` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_update_incident_status` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_incident_status`(
    IN p_incident_id INT,
    IN p_guard_id    INT,
    IN p_new_status  ENUM('open','under_review','resolved'),
    IN p_guard_note  TEXT
)
BEGIN
    DECLARE v_old_status  VARCHAR(20) DEFAULT NULL;
    DECLARE v_caller_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization: guard or admin only
    -- --------------------------------------------------------
    SELECT role INTO v_caller_role
    FROM   users
    WHERE  user_id             = p_guard_id
      AND  status              = 'active'
      AND  verification_status = 'verified';

    IF v_caller_role IS NULL OR v_caller_role NOT IN ('guard','admin') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Guard or admin account required.';
    END IF;

    -- --------------------------------------------------------
    -- Validate incident: must exist and not be soft-deleted
    -- --------------------------------------------------------
    SELECT status INTO v_old_status
    FROM   incidents
    WHERE  incident_id = p_incident_id
      AND  is_deleted  = 0;

    IF v_old_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Incident not found or has been deleted.';
    END IF;

    -- --------------------------------------------------------
    -- Business rule: only admin can re-open a resolved incident
    -- --------------------------------------------------------
    IF v_old_status = 'resolved'
       AND p_new_status <> 'resolved'
       AND v_caller_role <> 'admin'
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only admins can re-open a resolved incident.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic update + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        UPDATE incidents
        SET status            = p_new_status,
            assigned_guard_id = p_guard_id,
            guard_note        = p_guard_note,
            updated_at        = NOW()
        WHERE incident_id = p_incident_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            p_guard_id,
            'UPDATE_INCIDENT_STATUS',
            'incidents',
            p_incident_id,
            JSON_OBJECT('status', v_old_status),
            JSON_OBJECT(
                'status',     p_new_status,
                'guard_note', p_guard_note
            )
        );

    COMMIT;

    -- Return updated incident summary
    SELECT
        i.incident_id,
        i.status,
        i.guard_note,
        i.updated_at,
        CONCAT(g.first_name, ' ', g.last_name) AS responding_guard,
        'Incident status updated successfully.' AS message
    FROM  incidents i
    JOIN  users g ON i.assigned_guard_id = g.user_id
    WHERE i.incident_id = p_incident_id;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_update_payment_status` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_update_payment_status` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_payment_status`(
    IN p_mpesa_ref    VARCHAR(50),
    IN p_new_status   ENUM('completed','failed'),
    IN p_callback_ref VARCHAR(50)  -- Safaricom MpesaReceiptNumber (actual transaction ID)
                                   -- Different from CheckoutRequestID used in sp_create_payment
)
sp_update_payment_status: 
BEGIN
    DECLARE v_payment_id     INT          DEFAULT NULL;
    DECLARE v_user_id        INT          DEFAULT NULL;
    DECLARE v_old_status     VARCHAR(20)  DEFAULT NULL;
    DECLARE v_amount         DECIMAL(10,2)DEFAULT NULL;
    DECLARE v_sub_start      DATE         DEFAULT NULL;
    DECLARE v_sub_end        DATE         DEFAULT NULL;
    DECLARE v_existing_end   DATE         DEFAULT NULL;

    -- --------------------------------------------------------
    -- Input validation
    -- --------------------------------------------------------
    IF p_mpesa_ref IS NULL OR TRIM(p_mpesa_ref) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'M-Pesa reference is required.';
    END IF;

    IF p_new_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'New payment status is required.';
    END IF;

    -- --------------------------------------------------------
    -- Locate payment record by mpesa_ref
    -- --------------------------------------------------------
    SELECT payment_id, user_id, payment_status, amount
    INTO   v_payment_id, v_user_id, v_old_status, v_amount
    FROM   payments
    WHERE  mpesa_ref = TRIM(p_mpesa_ref);

    IF v_payment_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Payment record not found for this M-Pesa reference.';
    END IF;

    -- --------------------------------------------------------
    -- Idempotency check: payment must be in 'pending' state
    -- Safaricom may send duplicate callbacks. If payment is
    -- already completed or failed, silently return the current
    -- state rather than erroring — this handles duplicate
    -- callback delivery gracefully.
    -- --------------------------------------------------------
    IF v_old_status <> 'pending' THEN
        -- Return current state without error (idempotent)
        SELECT
            v_payment_id AS payment_id,
            v_old_status AS payment_status,
            'Payment has already been processed. No changes made.' AS message;
        LEAVE sp_update_payment_status; -- Exit cleanly
    END IF;

    -- --------------------------------------------------------
    -- Calculate subscription dates (completed payments only)
    -- --------------------------------------------------------
    IF p_new_status = 'completed' THEN

        -- Check if resident has an existing active subscription
        -- that ends in the future. If so, extend from that date
        -- rather than today — resident does not lose remaining days.
        SELECT MAX(subscription_end) INTO v_existing_end
        FROM   payments
        WHERE  user_id        = v_user_id
          AND  payment_status = 'completed'
          AND  subscription_end > CURDATE();

        IF v_existing_end IS NOT NULL THEN
            -- Extend from current subscription end date
            SET v_sub_start = v_existing_end + INTERVAL 1 DAY;
            SET v_sub_end   = v_existing_end + INTERVAL 30 DAY;
        ELSE
            -- New or lapsed subscription: start from today
            SET v_sub_start = CURDATE();
            SET v_sub_end   = CURDATE() + INTERVAL 30 DAY;
        END IF;

    END IF;

    -- --------------------------------------------------------
    -- Atomic update + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        UPDATE payments
        SET payment_status     = p_new_status,
            mpesa_ref          = COALESCE(TRIM(p_callback_ref), mpesa_ref),
            -- Update mpesa_ref to the actual MpesaReceiptNumber
            -- from the callback if provided, replacing the
            -- CheckoutRequestID used at creation.
            subscription_start = v_sub_start,
            subscription_end   = v_sub_end
        WHERE payment_id = v_payment_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            v_user_id,
            'UPDATE_PAYMENT_STATUS',
            'payments',
            v_payment_id,
            JSON_OBJECT('payment_status', v_old_status),
            JSON_OBJECT(
                'payment_status',     p_new_status,
                'subscription_start', v_sub_start,
                'subscription_end',   v_sub_end
            )
            -- mpesa_ref / callback_ref excluded from audit log
        );

    COMMIT;

    -- Return updated payment summary
    SELECT
        v_payment_id AS payment_id,
        p_new_status AS payment_status,
        v_sub_start  AS subscription_start,
        v_sub_end    AS subscription_end,
        CASE
            WHEN p_new_status = 'completed'
            THEN 'Payment confirmed. Subscription activated successfully.'
            ELSE 'Payment failed. The resident may initiate a new payment.'
        END AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_update_user_status` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_update_user_status` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_user_status`(
    IN p_admin_id       INT,
    IN p_target_user_id INT,
    IN p_new_status     ENUM('active','inactive')
)
BEGIN
    DECLARE v_old_status VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only active verified admins can update account status.';
    END IF;

    -- --------------------------------------------------------
    -- Prevent self-deactivation
    -- --------------------------------------------------------
    IF p_admin_id = p_target_user_id AND p_new_status = 'inactive' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Admins cannot deactivate their own account.';
    END IF;

    -- --------------------------------------------------------
    -- Validate target user
    -- --------------------------------------------------------
    SELECT status INTO v_old_status
    FROM   users
    WHERE  user_id = p_target_user_id;

    IF v_old_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'User not found.';
    END IF;

    IF v_old_status = p_new_status THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Account is already in the requested status.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic update + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        UPDATE users
        SET status     = p_new_status,
            updated_at = NOW()
        WHERE user_id = p_target_user_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            p_admin_id,
            'UPDATE_USER_STATUS',
            'users',
            p_target_user_id,
            JSON_OBJECT('status', v_old_status),
            JSON_OBJECT('status', p_new_status)
        );

    COMMIT;

    SELECT
        p_target_user_id AS user_id,
        CONCAT('Account has been set to ', p_new_status, '.') AS message;

END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_verify_resident` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_verify_resident` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_verify_resident`(
    IN p_admin_id          INT,
    IN p_target_user_id    INT,
    IN p_new_status        ENUM('verified','rejected')
)
BEGIN
    DECLARE v_old_status   VARCHAR(20) DEFAULT NULL;
    DECLARE v_current_role VARCHAR(20) DEFAULT NULL;

    -- --------------------------------------------------------
    -- Caller authorization
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM users
        WHERE user_id             = p_admin_id
          AND role                = 'admin'
          AND status              = 'active'
          AND verification_status = 'verified'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Access denied. Only active verified admins can verify residents.';
    END IF;

    -- --------------------------------------------------------
    -- Validate target user
    -- --------------------------------------------------------
    SELECT verification_status, role
    INTO   v_old_status, v_current_role
    FROM   users
    WHERE  user_id = p_target_user_id;

    IF v_old_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Resident not found.';
    END IF;

    IF v_current_role <> 'resident' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only resident accounts can be verified through this procedure.';
    END IF;

    IF v_old_status <> 'pending' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'This account has already been processed. Only pending accounts can be actioned.';
    END IF;

    -- --------------------------------------------------------
    -- Atomic update + audit log
    -- --------------------------------------------------------
    START TRANSACTION;

        UPDATE users
        SET verification_status = p_new_status,
            updated_at          = NOW()
        WHERE user_id = p_target_user_id;

        INSERT INTO audit_log (
            actor_id, action, target_table, target_id, old_value, new_value
        )
        VALUES (
            p_admin_id,
            'VERIFY_RESIDENT',
            'users',
            p_target_user_id,
            JSON_OBJECT('verification_status', v_old_status),
            JSON_OBJECT('verification_status', p_new_status)
        );

    COMMIT;

    SELECT
        p_target_user_id AS user_id,
        CONCAT('Resident account has been ', p_new_status, '.') AS message;

END */$$
DELIMITER ;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
