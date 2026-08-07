<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  public/index.php  — Front Controller / Router
//
//  Single entry point for all API requests.
//  Responsibilities:
//    1. Global exception handler (JSON error on uncaught exception)
//    2. CORS headers (set once, applies to all responses)
//    3. OPTIONS preflight handling
//    4. URL routing to correct controller + method
//    5. URL parameter extraction
// ============================================================

require_once __DIR__ . '/../helpers/Response.php';

// ---- 1. Global exception handler ----------------------------
// Catches any uncaught exception and returns a JSON error
// instead of a raw PHP error page.
set_exception_handler(function (Throwable $e) {
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.',
        'data'    => null,
    ]);
    exit;
});

// ---- 2. CORS headers ----------------------------------------
// Set once here — applies to every API response.
// Update 'Access-Control-Allow-Origin' to restrict to your
// specific frontend domain in production (not '*').
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// ---- 3. OPTIONS preflight -----------------------------------
// Return 204 immediately for preflight requests.
// Must happen BEFORE any authentication check.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- 4. URL routing -----------------------------------------
// Parse the request URI and strip the base path.
// Assumes the API is served from /api/ e.g.:
//   https://midview.app/api/auth/login
//   https://midview.app/api/incidents/5/status

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');

// Strip /api prefix
$uri    = preg_replace('#^/api#', '', $uri);
$parts  = explode('/', trim($uri, '/'));

$resource    = $parts[0] ?? '';
$id          = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
$sub         = $parts[2] ?? '';  // e.g. 'status', 'restore', 'respond'

// ---- 5. Route dispatch --------------------------------------

switch ($resource) {

    // --------------------------------------------------------
    //  AUTH ROUTES
    // --------------------------------------------------------
    case 'auth':
        require_once __DIR__ . '/../controllers/AuthController.php';
        $ctrl = new AuthController();

        if ($sub === 'register')         { $ctrl->register();                    break; }
        if ($sub === 'login')            { $ctrl->login();                       break; }
        if ($sub === 'staff')            { $ctrl->createStaffUser();             break; }
        if ($sub === 'pending')          { $ctrl->getPendingResidents();         break; }
        if ($sub === 'profile') {
            $ctrl->getProfile($id);
            break;
        }
        // /auth/verify/{id}
        if ($sub === 'verify' && $id !== null) {
            // id is the 3rd segment: /auth/verify/5
            $target = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : null;
            if (!$target) { Response::error('User ID required.', 400); }
            $ctrl->verifyResident($target);
            break;
        }
        // /auth/status/{id}
        if ($sub === 'status' && $id !== null) {
            $target = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : null;
            if (!$target) { Response::error('User ID required.', 400); }
            $ctrl->updateUserStatus($target);
            break;
        }
        Response::error('Auth endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  INCIDENT ROUTES
    // --------------------------------------------------------
    case 'incidents':
        require_once __DIR__ . '/../controllers/IncidentController.php';
        $ctrl = new IncidentController();

        if ($id === null) {
            match ($_SERVER['REQUEST_METHOD']) {
                'POST' => $ctrl->create(),
                'GET'  => $ctrl->index(),
                default => Response::error('Method not allowed.', 405),
            };
            break;
        }

        // /incidents/deleted (before numeric check)
        if ($parts[1] === 'deleted') { $ctrl->getDeleted();  break; }

        // /incidents/user or /incidents/user/{id}
        if ($parts[1] === 'user') {
            $uid = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : null;
            $ctrl->getByUser($uid);
            break;
        }

        // /incidents/{id}
        if ($sub === '') {
            match ($_SERVER['REQUEST_METHOD']) {
                'GET'    => $ctrl->show($id),
                'DELETE' => $ctrl->delete($id),
                default  => Response::error('Method not allowed.', 405),
            };
            break;
        }

        // /incidents/{id}/status
        if ($sub === 'status')  { $ctrl->updateStatus($id); break; }
        // /incidents/{id}/restore
        if ($sub === 'restore') { $ctrl->restore($id);      break; }

        Response::error('Incident endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  PANIC ROUTES
    // --------------------------------------------------------
    case 'panic':
        require_once __DIR__ . '/../controllers/PanicController.php';
        $ctrl = new PanicController();

        if ($id === null && $sub === '') {
            match ($parts[1] ?? '') {
                'active'     => $ctrl->getActive(),
                'history'    => $ctrl->history(),
                'my'         => $ctrl->myPanics(),
                'statistics' => $ctrl->statistics(),
                default      => match ($_SERVER['REQUEST_METHOD']) {
                    'POST'  => $ctrl->trigger(),
                    default => Response::error('Panic endpoint not found.', 404),
                },
            };
            break;
        }

        // /panic/{id}
        if ($sub === '')         { $ctrl->show($id);       break; }
        // /panic/{id}/respond
        if ($sub === 'respond')  { $ctrl->respond($id);    break; }
        // /panic/{id}/close
        if ($sub === 'close')    { $ctrl->close($id);      break; }

        Response::error('Panic endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  CCTV ROUTES
    // --------------------------------------------------------
    case 'cctv':
        require_once __DIR__ . '/../controllers/CctvController.php';
        $ctrl = new CctvController();

        if ($id === null && $sub === '') {
            match ($parts[1] ?? '') {
                'nearest'   => $ctrl->getNearest(),
                'summary'   => $ctrl->statusSummary(),
                default     => match ($_SERVER['REQUEST_METHOD']) {
                    'GET'  => $ctrl->index(),
                    'POST' => $ctrl->create(),
                    default => Response::error('Method not allowed.', 405),
                },
            };
            break;
        }

        // /cctv/{id}
        if ($sub === '') {
            match ($_SERVER['REQUEST_METHOD']) {
                'GET'    => $ctrl->show($id),
                'PUT'    => $ctrl->update($id),
                'DELETE' => $ctrl->delete($id),
                default  => Response::error('Method not allowed.', 405),
            };
            break;
        }

        // /cctv/{id}/status
        if ($sub === 'status') { $ctrl->toggleStatus($id); break; }
        // /cctv/{id}/audit
        if ($sub === 'audit')  { $ctrl->auditTrail($id);   break; }

        Response::error('CCTV endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  PAYMENT ROUTES
    // --------------------------------------------------------
    case 'payments':
        require_once __DIR__ . '/../controllers/PaymentController.php';
        $ctrl = new PaymentController();

        if ($id === null && $sub === '') {
            match ($parts[1] ?? '') {
                'initiate'     => $ctrl->initiate(),
                'callback'     => $ctrl->callback(),
                'subscription' => $ctrl->checkSubscription(),
                'overdue'      => $ctrl->overdue(),
                'statistics'   => $ctrl->statistics(),
                'history'      => $ctrl->history(),
                default        => match ($_SERVER['REQUEST_METHOD']) {
                    'GET' => $ctrl->index(),
                    default => Response::error('Payments endpoint not found.', 404),
                },
            };
            break;
        }

        // /payments/history/{user_id}
        if ($parts[1] === 'history' && $id !== null) {
            $uid = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : null;
            $ctrl->history($uid);
            break;
        }

        // /payments/{id}
        if ($sub === '') { $ctrl->show($id); break; }

        Response::error('Payments endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  POLICE ESCALATION ROUTES
    // --------------------------------------------------------
    case 'police':
        require_once __DIR__ . '/../controllers/PoliceController.php';
        $ctrl = new PoliceController();

        if ($id === null && $sub === '') {
            match ($parts[1] ?? '') {
                'escalate' => $ctrl->escalate(),
                default    => match ($_SERVER['REQUEST_METHOD']) {
                    'GET' => $ctrl->index(),
                    default => Response::error('Police endpoint not found.', 404),
                },
            };
            break;
        }

        // /police/incident/{incident_id}
        if ($parts[1] === 'incident' && isset($parts[2]) && is_numeric($parts[2])) {
            $ctrl->getByIncident((int)$parts[2]);
            break;
        }

        // /police/{id}
        if ($sub === '') { $ctrl->show($id); break; }

        // /police/{id}/status
        if ($sub === 'status') { $ctrl->updateStatus($id); break; }
        // /police/{id}/close
        if ($sub === 'close')  { $ctrl->close($id);        break; }

        Response::error('Police endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  SMS ROUTES
    // --------------------------------------------------------
    case 'sms':
        require_once __DIR__ . '/../controllers/SmsController.php';
        $ctrl = new SmsController();

        if ($id === null && $sub === '') {
            match ($parts[1] ?? '') {
                'log'        => $ctrl->log(),
                'statistics' => $ctrl->statistics(),
                default      => match ($_SERVER['REQUEST_METHOD']) {
                    'GET' => $ctrl->index(),
                    default => Response::error('SMS endpoint not found.', 404),
                },
            };
            break;
        }

        // /sms/incident/{incident_id}
        if ($parts[1] === 'incident' && isset($parts[2]) && is_numeric($parts[2])) {
            $ctrl->getByIncident((int)$parts[2]);
            break;
        }

        Response::error('SMS endpoint not found.', 404);
        break;

    // --------------------------------------------------------
    //  REPORT ROUTES
    // --------------------------------------------------------
    case 'reports':
        require_once __DIR__ . '/../controllers/ReportController.php';
        $ctrl = new ReportController();

        match ($parts[1] ?? '') {
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