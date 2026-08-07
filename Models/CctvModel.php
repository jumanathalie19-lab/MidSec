<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/CctvModel.php
//  Calls: sp_add_cctv_feed, sp_update_cctv_feed,
//         sp_toggle_cctv_feed_status, sp_delete_cctv_feed,
//         sp_get_all_cctv_feeds, sp_get_cctv_by_id,
//         sp_get_nearest_cctv, sp_get_cctv_feed_status_summary,
//         sp_get_cctv_audit_trail
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class CctvModel extends BaseModel {

    // ----------------------------------------------------------
    //  [C1] addFeed()
    //  Calls: sp_add_cctv_feed
    //
    //  FIX: $admin_id added as first parameter.
    //  Admin only — enforced inside the procedure.
    //  Procedure also checks for duplicate feed_name and
    //  duplicate stream_url before inserting.
    //  New feeds are created with feed_status = 'active'.
    //  GPS uses 'd' (double) bind type → DECIMAL(10,8)/DECIMAL(11,8).
    // ----------------------------------------------------------
    public function addFeed(
        int    $admin_id,
        string $feed_name,
        string $location,
        string $stream_url,
        float  $latitude,
        float  $longitude
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_add_cctv_feed(?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isssdd',
            $admin_id,
            $feed_name,
            $location,
            $stream_url,
            $latitude,
            $longitude
        );

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [C2] updateFeed()
    //  Calls: sp_update_cctv_feed
    //
    //  FIX: $admin_id added as first parameter.
    //  Admin only — enforced inside the procedure.
    //  Procedure checks for duplicate feed_name and stream_url
    //  excluding the current feed (allows other fields to be
    //  updated without triggering false duplicate errors).
    //  Records old and new values in audit_log.
    // ----------------------------------------------------------
    public function updateFeed(
        int    $admin_id,
        int    $feed_id,
        string $feed_name,
        string $location,
        string $stream_url,
        float  $latitude,
        float  $longitude
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_update_cctv_feed(?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iisssdd',
            $admin_id,
            $feed_id,
            $feed_name,
            $location,
            $stream_url,
            $latitude,
            $longitude
        );

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [C3] toggleFeedStatus()
    //  Calls: sp_toggle_cctv_feed_status
    //         (replaces dropped sp_deactivate_cctv_feed)
    //
    //  FIX 1: Calls the correct replacement procedure.
    //          sp_deactivate_cctv_feed was dropped in Section 5.
    //  FIX 2: $admin_id and $reason parameters added.
    //  FIX 3: Now bidirectional — toggles between 'active' and
    //          'inactive'. The original could only deactivate.
    //  $reason is mandatory — procedure SIGNALs if empty.
    //  Procedure returns an operational impact warning if any
    //  active panic alerts are linked to the feed being toggled.
    // ----------------------------------------------------------
    public function toggleFeedStatus(
        int    $admin_id,
        int    $feed_id,
        string $reason    // mandatory: documented justification
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_toggle_cctv_feed_status(?, ?, ?)"
        );
        $stmt->bind_param('iis', $admin_id, $feed_id, $reason);

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [C4] deleteFeed()
    //  Calls: sp_delete_cctv_feed
    //
    //  FIX 1: $admin_id and $reason parameters added.
    //  FIX 2: bind string updated from 'i' to 'iis'.
    //
    //  IMPORTANT — Two-step deletion process:
    //  The procedure requires the feed to be in 'inactive' status
    //  before deletion is allowed. The controller must call
    //  toggleFeedStatus() first (to deactivate), then deleteFeed().
    //  Attempting to delete an active feed will SIGNAL an error.
    //  The procedure also blocks deletion if any incidents or
    //  panic alerts reference this feed (evidence preservation).
    //  $reason is mandatory — procedure SIGNALs if empty.
    // ----------------------------------------------------------
    public function deleteFeed(
        int    $admin_id,
        int    $feed_id,
        string $reason    // mandatory: documented justification
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_delete_cctv_feed(?, ?, ?)"
        );
        $stmt->bind_param('iis', $admin_id, $feed_id, $reason);

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [C5] getAllFeeds()
    //  Calls: sp_get_all_cctv_feeds
    //
    //  FIX: $requesting_user_id and $status_filter added.
    //  Role-aware stream_url disclosure:
    //    resident  → stream_url returned as NULL
    //    guard     → stream_url returned (needed for live feed)
    //    admin     → stream_url returned
    //  $status_filter: NULL = all feeds, 'active' or 'inactive'
    //  for filtered admin maintenance views.
    // ----------------------------------------------------------
    public function getAllFeeds(
        int     $requesting_user_id,
        ?string $status_filter = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_all_cctv_feeds(?, ?)"
        );
        $stmt->bind_param('is', $requesting_user_id, $status_filter);

        return $this->fetchAll($stmt);
    }

    // ----------------------------------------------------------
    //  [C6] getFeedById()
    //  Calls: sp_get_cctv_by_id
    //
    //  FIX: $requesting_user_id added as first parameter.
    //  Role-aware stream_url disclosure — same rules as getAllFeeds().
    //  Procedure validates feed exists before returning data.
    // ----------------------------------------------------------
    public function getFeedById(
        int $requesting_user_id,
        int $feed_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_cctv_by_id(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $feed_id);

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [C7] getNearestFeed()
    //  Calls: sp_get_nearest_cctv
    //
    //  FIX: $requesting_user_id added as first parameter.
    //  Guard or admin only — this procedure reveals the
    //  geographical distribution of the camera network.
    //  stream_url is NOT returned by this procedure (removed
    //  in Section 5 — it is used server-side only to store
    //  the feed_id reference; stream URL is retrieved
    //  separately when the guard actively needs it).
    //  Returns: feed_id, feed_name, location, latitude,
    //           longitude, feed_status, distance_approx.
    // ----------------------------------------------------------
    public function getNearestFeed(
        int   $requesting_user_id,
        float $latitude,
        float $longitude
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_nearest_cctv(?, ?, ?)"
        );
        $stmt->bind_param('idd', $requesting_user_id, $latitude, $longitude);

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [C8] getFeedStatusSummary()  — NEW
    //  Calls: sp_get_cctv_feed_status_summary
    //
    //  Admin only — enforced inside the procedure.
    //  Returns TWO result sets — uses fetchMultiple():
    //    [0] Overall counts: total, active, inactive feeds
    //    [1] Per-feed activity: linked incident and panic counts,
    //        open incidents, active panics per feed
    //  Used for the admin operational dashboard CCTV overview.
    //  No stream_url in response — aggregate statistics only.
    // ----------------------------------------------------------
    public function getFeedStatusSummary(int $admin_id): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_cctv_feed_status_summary(?)"
        );
        $stmt->bind_param('i', $admin_id);

        return $this->fetchMultiple($stmt);
    }

    // ----------------------------------------------------------
    //  [C9] getCctvAuditTrail()  — NEW
    //  Calls: sp_get_cctv_audit_trail
    //
    //  Admin only — enforced inside the procedure.
    //  Returns the complete audit history for a specific feed:
    //  every add, update, toggle, and delete action taken on
    //  that feed, with actor name, role, and timestamp.
    //  Used for investigating why a camera was offline during
    //  a security event and for KDPA 2019 compliance review.
    //  Queries the audit_log table — works even if the feed
    //  record itself has been deleted.
    // ----------------------------------------------------------
    public function getCctvAuditTrail(
        int $admin_id,
        int $feed_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_cctv_audit_trail(?, ?)"
        );
        $stmt->bind_param('ii', $admin_id, $feed_id);

        return $this->fetchAll($stmt);
    }
}