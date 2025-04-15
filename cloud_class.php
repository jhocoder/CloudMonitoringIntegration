<?php
// Permitir peticiones desde cualquier origen (útil para desarrollo)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// require_once __DIR__ . '/vendor/autoload.php';

use Google\Cloud\Monitoring\V3\Client\MetricServiceClient;
use Google\Cloud\Monitoring\V3\ListTimeSeriesRequest;
use Google\Cloud\Monitoring\V3\Aggregation;
use Google\Protobuf\Timestamp;
use Google\Protobuf\Duration;
use Google\Cloud\Monitoring\V3\TimeInterval;

class GoogleMonitoringService
{
    private $client;
    private $projectId;

    public function __construct(string $credentialsPath, string $projectId)
    {
        $this->projectId = $projectId;

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . realpath($credentialsPath));

        $this->client = new MetricServiceClient([
            'credentials' => $credentialsPath
        ]);
    }

    public function getRequestCountMetrics(int $secondsBack = 86400, int $aggregationSeconds = 3600): array
    {
        $formattedProjectName = 'projects/' . $this->projectId;

        $startDateTime = new DateTime('2025-04-15 00:00:00', new DateTimeZone('UTC'));
        $startTime = new Timestamp();
        $startTime->setSeconds($startDateTime->getTimestamp());

        $endTime = new Timestamp();
        $endTime->setSeconds(time());

        $timeInterval = new TimeInterval();
        $timeInterval->setStartTime($startTime);
        $timeInterval->setEndTime($endTime);

        $aggregation = new Aggregation();
        $alignmentPeriod = new Duration();
        $alignmentPeriod->setSeconds($aggregationSeconds);
        $aggregation->setAlignmentPeriod($alignmentPeriod);
        $aggregation->setPerSeriesAligner(Aggregation\Aligner::ALIGN_RATE);

        $request = new ListTimeSeriesRequest();
        $request->setName($formattedProjectName);
        $request->setFilter('metric.type="maps.googleapis.com/service/v2/request_count"');
        $request->setInterval($timeInterval);
        $request->setAggregation($aggregation);

        $response = $this->client->listTimeSeries($request);

        $metrics = [];

        foreach ($response as $timeSeries) {
            $points = $timeSeries->getPoints();
            $resourceLabels = $timeSeries->getResource()->getLabels();
            $metricLabels = $timeSeries->getMetric()->getLabels();

            foreach ($points as $point) {
                $value = $point->getValue()->getDoubleValue();
                if ($value === null) {
                    $value = $point->getValue()->getInt64Value();
                }

                $metrics[] = [
                    'metricType'   => $timeSeries->getMetric()->getType(),
                    'resourceType' => $timeSeries->getResource()->getType(),
                    'projectId'    => $resourceLabels['project_id'] ?? null,
                    'location'     => $resourceLabels['location'] ?? null,
                    'service'      => $resourceLabels['service'] ?? null,
                    'method'       => $metricLabels['method'] ?? null,
                    'consumerId'   => $metricLabels['consumer_id'] ?? null,
                    'time'         => $point->getInterval()->getStartTime()->toDateTime()->format('Y-m-d H:i:s'),
                    'value'        => $value,
                ];
            }
        }

        return $metrics;
    }

    public function close()
    {
        $this->client->close();
    }
}
