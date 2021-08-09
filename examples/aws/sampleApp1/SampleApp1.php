<?php
/*
 * Copyright The OpenTelemetry Authors
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Examples\Aws\SampleApp1;

require __DIR__ . '/../../../vendor/autoload.php';

use GuzzleHttp\Client;
use Instrumentation\Aws\Xray\AwsXrayIdGenerator;
use OpenTelemetry\Contrib\OtlpGrpc\Exporter as OTLPExporter;
use OpenTelemetry\Sdk\Trace\PropagationMap;
use OpenTelemetry\Sdk\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\Sdk\Trace\TracerProvider;
use OpenTelemetry\Trace as API;
use Propagators\Aws\Xray\AwsXrayPropagator;

use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextKey;

/**
 * This is a sample app that makes an http request to aws.amazon.com
 * It uses the OTEL GRPC Exporter
 * Sends traces to the aws-otel-collector
 * It will generate one trace that has a child span and uses the
 * AWS X-Ray propagator to inject the context into the carrier.
 */

echo 'Starting Sample App' . PHP_EOL;

$Exporter = new OTLPExporter();
$client = new Client();

// Create a tracer object that uses the AWS X-Ray ID Generator to
// generate trace Ids in the correct format
$tracer = (new TracerProvider(null, null, new AwsXrayIdGenerator()))
    ->addSpanProcessor(new SimpleSpanProcessor($Exporter))
    ->getTracer('io.opentelemetry.contrib.php');

// Create a span with the tracer
$span = $tracer->startAndActivateSpan('session.generate.span.' . microtime(true));

// Add some dummy attributes to the parent span (also the root span)
$span->setAttribute('item_A', 'cars')
->setAttribute('item_B', 'motorcycles')
->setAttribute('item_C', 'planes');

// Create a child span to demonstrate propagation
$childSpan = $tracer->startAndActivateSpan('session.generate.child.span.' . microtime(true), API\SpanKind::KIND_CLIENT);

// Create a carrier and map to inject the context of the child span into the carrier
$carrier = [];
$map = new PropagationMap();
AwsXrayPropagator::inject($childSpan->getContext(), $carrier, $map);

// Make an HTTP request to take some time up
// Carrier is injected into the header to simulate a microservice needing the carrier
try {
    $res = $client->request('GET', 'https://aws.amazon.com', ['headers' => $carrier, 'timeout' => 2000,]);
} catch (\Throwable $e) {
    echo $e->getMessage() . PHP_EOL;

    return null;
}

// Format and output the trace Id of the childspan
$traceId = $childSpan->getContext()->getTraceId();
$xrayTraceId = '1-' . substr($traceId, 0, 8) . '-' . substr($traceId, 8);
echo 'Final trace ID: ' . json_encode(['traceId' => $xrayTraceId]);

// End both the child and root span to be able to export the trace.
$childSpan->end();
$span->end();

echo PHP_EOL . 'Sample App complete!';
echo PHP_EOL;
