<?php
require_once 'cloud_class.php';

header('Content-Type: application/json');


try {
    $service = new GoogleMonitoringService($credentialsPath, $projectId);
    $metrics = $service->getRequestCountMetrics();

    echo json_encode([
        'status' => 'success',
        'data' => $metrics
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($service)) {
        $service->close();
    }
}
