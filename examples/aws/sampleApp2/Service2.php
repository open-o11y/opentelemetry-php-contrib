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

namespace Examples\Aws\SampleApp2;

require __DIR__ . '/../../../vendor/autoload.php';

use OpenTelemetry\Sdk\Trace\PropagationMap;
use OpenTelemetry\Trace as API;
use Propagators\Aws\Xray\AwsXrayPropagator;

class Service2
{
    private const LIMIT = 50;

    private $tracer;
    private $carrier;

    public function __construct(API\Tracer $tracer, array $carrier)
    {
        $this->tracer = $tracer;
        $this->carrier = $carrier;
    }

    public function useService() {
        // Extract the SpanContext from the carrier
        $map = new PropagationMap();
        $context = AwsXrayPropagator::extract($this->carrier, $map);

        // Do some kind of operation
        $i = 0;
        $j = 0;
        while ($i < self::LIMIT) {
            while ($j < self::LIMIT) {
                $j += $i + $j;
                $j++;
            }
            $i++;
        }

        // Create a child span
        $childSpan = $this->tracer->startActiveSpan('session.second.child.span' . microtime(true), $context, false, API\SpanKind::KIND_CLIENT);

        // Set some dummy attributes
        $childSpan->setAttribute('service_2', 'microservice')
                ->setAttribute('action_item', strval($j));

        // End child span
        $childSpan->end();

        return $childSpan->getContext();
    }
}
