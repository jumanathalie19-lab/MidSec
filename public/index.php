<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  public/index.php — Front Controller / Router
// ============================================================

require_once __DIR__ . '/../helpers/Response.php';

// ---- Global exception handler ------------------------------
set_exception_handler(function (Throwable $e) {
    error_log('Uncaught exception: ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.',
        'data'    => null,
    ]);
    exit;
});

// ---- CORS headers ------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// ---- OPTIONS preflight -------------------------------------
// Must return 204 BEFORE any auth check
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Parse URL ---------------------------------------------
$uri   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri   = rtrim($uri, '/');

// Strip /MIDSEC/api prefix — adjust if your folder name differs
$uri   = preg_replace('#^/MIDSEC/api#', '', $uri);
$uri   = preg_replace('#^/api#',        '', $uri); // fallback

$parts = explode('/', trim($uri, '/'));

$resource = $parts[0] ?? '';
$id       = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
$sub      = $parts[2] ?? '';

// ---- Route dispatch ----------------------------------------
switch ($resource) {

    // --------------------------------------------------------
    //  AUTH
    // --------------------------------------------------------
    case 'auth':
        require_once __DIR__ . '/../controllers/AuthController.php';
        $ctrl = new AuthController();

        $segment = $parts[1] ?? '';

        if ($segment === 'register')  { $ctrl->register();              break; }
        if ($segment === 'bootstrap') { $ctrl->bootstrapFirstAdmin();    break; }
        if ($segment === 'login')     { $ctrl->login();                 break; }
        if ($segment === 'pending')   { $ctrl->getPendingResidents();   break; }
        if ($segment === 'residents') { $ctrl->getAllResidents();       break; }

        if ($segment === 'staff') {
            match ($_SERVER['REQUEST_METHOD']) {
                'POST'  => $ctrl->createStaffUser(),
                'GET'   => $ctrl->getAllStaff(),
                default => Response::error('Method not allowed.', 405),
            };
            break;
        }

        // /auth/profile  or  /auth/profile/{id}
        if ($segment === 'profile') {
            $profileId = isset($parts[2]) && is_numeric($parts[2])
                ? (int)$parts[2] : null;
            $ctrl->getProfile($profileId);
            break;
        }

        // /auth/verify/{id}
        if ($segment === 'verify') {
            $target = isset($parts[2]) && is_numeric($parts[2])
                ? (int)$parts[2] : null;
            if (!$target) Response::error('User ID required.', 400);
            $ctrl->verifyResident($target);
            break;
        }

        // /auth/status/{id}
        if ($segment === 'status') {
            $target = isset($parts[2]) && is_numeric($parts[2])
                ? (int)$parts[2] : null;
            if (!$target) Response::error('User ID required.', 400);
            $ctrl->updateUserStatus($target);
            break;
        }

        Response::error('Auth endpoint not found.', 404);
        break;


        
    // --------------------------------------------------------
    //  INCIDENTS
    // --------------------------------------------------------
    case 'incidents':
        require_once __DIR__ . '/../controllers/IncidentController.php';
        $ctrl    = new IncidentController();
        $segment = $parts[1] ?? '';

        // /incidents  (POST = create, GET = all)
        if ($segment === '') {
            match ($_SERVER['REQUEST_METHOD']) {
                'POST' => $ctrl->create(),
                'GET'  => $ctrl->index(),
                default => Response::error('Method not allowed.', 405),
            };
            break;
        }

        // /incidents/deleted
        if ($segment === 'deleted') { $ctrl->getDeleted(); break; }

        // /incidents/user  or  /incidents/user/{id}
        if ($segment === 'user') {
            $uid = isset($parts[2]) && is_numeric($parts[2])
                ? (int)$parts[2] : null;
            $ctrl->getByUser($uid);
            break;
        }

        // /incidents/{id}
        if (is_numeric($segment)) {
            $iid = (int)$segment;
            $action = $parts[2] ?? '';

            if ($action === '') {
                match ($_SERVER['REQUEST_METHOD']) {
                    'GET'    => $ctrl->show($iid),
                    'DELETE' => $ctrl->delete($iid),
                    default  => Response::error('Method not allowed.', 405),
                };
                break;
            }
            if ($action === 'status')  { $ctrl->updateStatus($iid); break; }
            if ($action === 'restore') { $ctrl->restore($iid);      break; }
        }

        Response::error('Incident endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  PANIC
    // --------------------------------------------------------
    case 'panic':
        require_once __DIR__ . '/../controllers/PanicController.php';
        $ctrl    = new PanicController();
        $segment = $parts[1] ?? '';

        if ($segment === '')          {
            match ($_SERVER['REQUEST_METHOD']) {
                'POST' => $ctrl->trigger(),
                default => Response::error('Panic endpoint not found.', 404),
            };
            break;
        }
        if ($segment === 'active')     { $ctrl->getActive();     break; }
        if ($segment === 'history')    { $ctrl->history();       break; }
        if ($segment === 'my')         { $ctrl->myPanics();      break; }
        if ($segment === 'statistics') { $ctrl->statistics();    break; }

        // /panic/{id}
        if (is_numeric($segment)) {
            $aid    = (int)$segment;
            $action = $parts[2] ?? '';
            if ($action === '')         { $ctrl->show($aid);       break; }
            if ($action === 'respond')  { $ctrl->respond($aid);    break; }
            if ($action === 'close')    { $ctrl->close($aid);      break; }
        }

        Response::error('Panic endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  CCTV
    // --------------------------------------------------------
    case 'cctv':
        require_once __DIR__ . '/../controllers/CctvController.php';
        $ctrl    = new CctvController();
        $segment = $parts[1] ?? '';

        if ($segment === '') {
            match ($_SERVER['REQUEST_METHOD']) {
                'GET'  => $ctrl->index(),
                'POST' => $ctrl->create(),
                default => Response::error('Method not allowed.', 405),
            };
            break;
        }
        if ($segment === 'nearest') { $ctrl->getNearest();      break; }
        if ($segment === 'summary') { $ctrl->statusSummary();   break; }

        // /cctv/{id}
        if (is_numeric($segment)) {
            $fid    = (int)$segment;
            $action = $parts[2] ?? '';
            if ($action === '') {
                match ($_SERVER['REQUEST_METHOD']) {
                    'GET'    => $ctrl->show($fid),
                    'PUT'    => $ctrl->update($fid),
                    'DELETE' => $ctrl->delete($fid),
                    default  => Response::error('Method not allowed.', 405),
                };
                break;
            }
            if ($action === 'status') { $ctrl->toggleStatus($fid); break; }
            if ($action === 'audit')  { $ctrl->auditTrail($fid);   break; }
        }

        Response::error('CCTV endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  PAYMENTS
    // --------------------------------------------------------
    case 'payments':
        require_once __DIR__ . '/../controllers/PaymentController.php';
        $ctrl    = new PaymentController();
        $segment = $parts[1] ?? '';

        if ($segment === '') {
            match ($_SERVER['REQUEST_METHOD']) {
                'GET' => $ctrl->index(),
                default => Response::error('Payments endpoint not found.', 404),
            };
            break;
        }
        if ($segment === 'initiate')     { $ctrl->initiate();          break; }
        if ($segment === 'callback')     { $ctrl->callback();          break; }
        if ($segment === 'subscription') { $ctrl->checkSubscription(); break; }
        if ($segment === 'overdue')      { $ctrl->overdue();           break; }
        if ($segment === 'statistics')   { $ctrl->statistics();        break; }

        // /payments/history  or  /payments/history/{id}
        if ($segment === 'history') {
            $uid = isset($parts[2]) && is_numeric($parts[2])
                ? (int)$parts[2] : null;
            $ctrl->history($uid);
            break;
        }

        // /payments/{id}
        if (is_numeric($segment)) {
            $ctrl->show((int)$segment);
            break;
        }

        Response::error('Payments endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  POLICE
    // --------------------------------------------------------
    case 'police':
        require_once __DIR__ . '/../controllers/PoliceController.php';
        $ctrl    = new PoliceController();
        $segment = $parts[1] ?? '';

        if ($segment === '') {
            match ($_SERVER['REQUEST_METHOD']) {
                'GET' => $ctrl->index(),
                default => Response::error('Police endpoint not found.', 404),
            };
            break;
        }
        if ($segment === 'escalate') { $ctrl->escalate(); break; }

        // /police/incident/{incident_id}
        if ($segment === 'incident') {
            $iid = isset($parts[2]) && is_numeric($parts[2])
                ? (int)$parts[2] : null;
            if (!$iid) Response::error('Incident ID required.', 400);
            $ctrl->getByIncident($iid);
            break;
        }

        // /police/{id}
        if (is_numeric($segment)) {
            $eid    = (int)$segment;
            $action = $parts[2] ?? '';
            if ($action === '')       { $ctrl->show($eid);          break; }
            if ($action === 'status') { $ctrl->updateStatus($eid);  break; }
            if ($action === 'close')  { $ctrl->close($eid);         break; }
        }

        Response::error('Police endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  SMS
    // --------------------------------------------------------
    case 'sms':
        require_once __DIR__ . '/../controllers/SmsController.php';
        $ctrl    = new SmsController();
        $segment = $parts[1] ?? '';

        if ($segment === '') {
            match ($_SERVER['REQUEST_METHOD']) {
                'GET' => $ctrl->index(),
                default => Response::error('SMS endpoint not found.', 404),
            };
            break;
        }
        if ($segment === 'log')        { $ctrl->log();        break; }
        if ($segment === 'statistics') { $ctrl->statistics(); break; }

        // /sms/incident/{incident_id}
        if ($segment === 'incident') {
            $iid = isset($parts[2]) && is_numeric($parts[2])
                ? (int)$parts[2] : null;
            if (!$iid) Response::error('Incident ID required.', 400);
            $ctrl->getByIncident($iid);
            break;
        }

        Response::error('SMS endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  REPORTS
    // --------------------------------------------------------
    case 'reports':
        require_once __DIR__ . '/../controllers/ReportController.php';
        $ctrl    = new ReportController();
        $segment = $parts[1] ?? '';

        match ($segment) {
            'monthly-incidents' => $ctrl->monthlyIncidents(),
            'crime-trends'      => $ctrl->crimeTrends(),
            'panic-statistics'  => $ctrl->panicStatistics(),
            'resident-activity' => $ctrl->residentActivity(),
            'guard-performance' => $ctrl->guardPerformance(),
            'payment-summary'   => $ctrl->paymentSummary(),
            'full-summary'      => $ctrl->fullSecuritySummary(),
            'record'            => $ctrl->generateReportRecord(),
            default             => Response::error('Report endpoint not found.', 404),
        };
        break;

    // --------------------------------------------------------
    //  404 — No matching resource
    // --------------------------------------------------------
    default:
        Response::error('API endpoint not found.', 404);
        break;
}