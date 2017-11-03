<?php
/*
 *
 * Copyright 2015 gRPC authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace Grpc;

/**
 * Class AbstractCall.
 * @package Grpc
 */
abstract class AbstractCall
{
    /**
     * @var Call
     */
    protected $call;
    protected $deserialize;
    protected $metadata;
    protected $trailing_metadata;

    /**
     * Create a new Call wrapper object.
     *
     * @param Channel  $channel     The channel to communicate on
     * @param string   $method      The method to call on the
     *                              remote server
     * @param callback $deserialize A callback function to deserialize
     *                              the response
     * @param array    $options     Call options (optional)
     */
    public function __construct(Channel $channel,
                                $method,
                                $deserialize,
                                array $options = [])
    {
        if (array_key_exists('timeout', $options) &&
            is_numeric($timeout = $options['timeout'])
        ) {
            \QMetric::startNonoverlappingBenchmark('spanner.app_time.grpc');
            $now = Timeval::now();
            $delta = new Timeval($timeout);
            $deadline = $now->add($delta);
            \QMetric::endNonoverlappingBenchmark('spanner.app_time.grpc');
        } else {
            \QMetric::startNonoverlappingBenchmark('spanner.app_time.grpc');
            $deadline = Timeval::infFuture();
            \QMetric::endNonoverlappingBenchmark('spanner.app_time.grpc');
        }

        \QMetric::startNonoverlappingBenchmark('spanner.app_time.grpc');
        $this->call = new Call($channel, $method, $deadline);
        \QMetric::endNonoverlappingBenchmark('spanner.app_time.grpc');

        $this->deserialize = $deserialize;
        $this->metadata = null;
        $this->trailing_metadata = null;
        if (array_key_exists('call_credentials_callback', $options) &&
            is_callable($call_credentials_callback =
                $options['call_credentials_callback'])
        ) {
            \QMetric::startNonoverlappingBenchmark('spanner.app_time.grpc');
            $call_credentials = CallCredentials::createFromPlugin(
                $call_credentials_callback
            );
            \QMetric::endNonoverlappingBenchmark('spanner.app_time.grpc');

            \QMetric::startNonoverlappingBenchmark('spanner.app_time.grpc');
            $this->call->setCredentials($call_credentials);
            \QMetric::endNonoverlappingBenchmark('spanner.app_time.grpc');
        }
    }

    /**
     * @return mixed The metadata sent by the server
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @return mixed The trailing metadata sent by the server
     */
    public function getTrailingMetadata()
    {
        return $this->trailing_metadata;
    }

    /**
     * @return string The URI of the endpoint
     */
    public function getPeer()
    {
        \QMetric::startNonoverlappingBenchmark('spanner.app_time.grpc');
        $peer = $this->call->getPeer();
        \QMetric::endNonoverlappingBenchmark('spanner.app_time.grpc');
        return $peer;
    }

    /**
     * Cancels the call.
     */
    public function cancel()
    {
        \QMetric::startNonoverlappingBenchmark('spanner.app_time.grpc');
        $this->call->cancel();
        \QMetric::endNonoverlappingBenchmark('spanner.app_time.grpc');
    }

    /**
     * Serialize a message to the protobuf binary format.
     *
     * @param mixed $data The Protobuf message
     *
     * @return string The protobuf binary format
     */
    protected function _serializeMessage($data)
    {
        // Proto3 implementation
        if (method_exists($data, 'encode')) {
            return $data->encode();
        } elseif (method_exists($data, 'serializeToString')) {
            return $data->serializeToString();
        }

        // Protobuf-PHP implementation
        return $data->serialize();
    }

    /**
     * Deserialize a response value to an object.
     *
     * @param string $value The binary value to deserialize
     *
     * @return mixed The deserialized value
     */
    protected function _deserializeResponse($value)
    {
        if ($value === null) {
            return;
        }

        // Proto3 implementation
        if (is_array($this->deserialize)) {
            list($className, $deserializeFunc) = $this->deserialize;
            $obj = new $className();
            if (method_exists($obj, $deserializeFunc)) {
                $obj->$deserializeFunc($value);
            } else {
                $obj->mergeFromString($value);
            }

            return $obj;
        }

        // Protobuf-PHP implementation
        return call_user_func($this->deserialize, $value);
    }

    /**
     * Set the CallCredentials for the underlying Call.
     *
     * @param CallCredentials $call_credentials The CallCredentials object
     */
    public function setCallCredentials($call_credentials)
    {
        \QMetric::startNonoverlappingBenchmark('spanner.app_time.grpc');
        $this->call->setCredentials($call_credentials);
        \QMetric::endNonoverlappingBenchmark('spanner.app_time.grpc');
    }
}
