<?php

namespace Seatsio\Events;

use Seatsio\PageFetcher;
use Seatsio\SeatsioJsonMapper;

class Events
{

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @param $chartKey string
     * @param $eventKey string
     * @param $bookWholeTables boolean
     * @return Event
     */
    public function create($chartKey, $eventKey = null, $bookWholeTables = null)
    {
        $request = new \stdClass();
        $request->chartKey = $chartKey;
        if ($eventKey !== null) {
            $request->eventKey = $eventKey;
        }
        if ($bookWholeTables !== null) {
            $request->bookWholeTables = $bookWholeTables;
        }
        $res = $this->client->post('/events', ['json' => $request]);
        $json = \GuzzleHttp\json_decode($res->getBody());
        $mapper = SeatsioJsonMapper::create();
        return $mapper->map($json, new Event());
    }

    /**
     * @param $key string
     * @return Event
     */
    public function retrieve($key)
    {
        $res = $this->client->get('/events/' . $key);
        $json = \GuzzleHttp\json_decode($res->getBody());
        $mapper = SeatsioJsonMapper::create();
        return $mapper->map($json, new Event());
    }

    /**
     * @param $key string
     * @param $chartKey string
     * @param $eventKey string
     * @param $bookWholeTables string
     * @return void
     */
    public function update($key, $chartKey = null, $eventKey = null, $bookWholeTables = null)
    {
        $request = new \stdClass();
        if ($chartKey !== null) {
            $request->chartKey = $chartKey;
        }
        if ($eventKey !== null) {
            $request->eventKey = $eventKey;
        }
        if ($bookWholeTables !== null) {
            $request->bookWholeTables = $bookWholeTables;
        }
        $this->client->post('/events/' . $key, ['json' => $request]);
    }

    /**
     * @return EventLister
     */
    public function iterator()
    {
        return new EventLister(new PageFetcher('/events', $this->client, function () {
            return new EventPage();
        }));
    }

    /**
     * @param $key string
     * @param $objectId string
     * @return StatusChangeLister
     */
    public function statusChanges($key, $objectId = null)
    {
        if ($objectId === null) {
            return new StatusChangeLister(new PageFetcher('/events/' . $key . '/status-changes', $this->client, function () {
                return new StatusChangePage();
            }));
        }
        return new StatusChangeLister(new PageFetcher('/events/' . $key . '/objects/' . $objectId . '/status-changes', $this->client, function () {
            return new StatusChangePage();
        }));
    }

    /**
     * @param $key string
     * @param $objects string[]
     * @param $categories string[]
     * @return void
     */
    public function markAsForSale($key, $objects = null, $categories = null)
    {
        $request = new \stdClass();
        if ($objects !== null) {
            $request->objects = $objects;
        }
        if ($categories !== null) {
            $request->categories = $categories;
        }
        $this->client->post('/events/' . $key . '/actions/mark-as-for-sale', ['json' => $request]);
    }

    /**
     * @param $key string
     * @param $objects string[]
     * @param $categories string[]
     * @return void
     */
    public function markAsNotForSale($key, $objects = null, $categories = null)
    {
        $request = new \stdClass();
        if ($objects !== null) {
            $request->objects = $objects;
        }
        if ($categories !== null) {
            $request->categories = $categories;
        }
        $this->client->post('/events/' . $key . '/actions/mark-as-not-for-sale', ['json' => $request]);
    }

    /**
     * @param $key string
     * @return void
     */
    public function markEverythingAsForSale($key)
    {
        $this->client->post('/events/' . $key . '/actions/mark-everything-as-for-sale');
    }

    /**
     * @param $key string
     * @param $object string
     * @param $extraData object|array
     * @return void
     */
    public function updateExtraData($key, $object, $extraData)
    {
        $request = new \stdClass();
        $request->extraData = $extraData;
        $this->client->post(
            \GuzzleHttp\uri_template('/events/{key}/objects/{object}/actions/update-extra-data', ["key" => $key, "object" => $object]),
            ['json' => $request]
        );
    }

    /**
     * @param $key string
     * @param $object string
     * @return ObjectStatus
     */
    public function getObjectStatus($key, $object)
    {
        $res = $this->client->get(\GuzzleHttp\uri_template('/events/{key}/objects/{object}', ["key" => $key, "object" => $object]));
        $json = \GuzzleHttp\json_decode($res->getBody());
        $mapper = SeatsioJsonMapper::create();
        return $mapper->map($json, new ObjectStatus());
    }

    /**
     * @param $keyOrKeys string|string[]
     * @param $objectOrObjects mixed
     * @param $status string
     * @param $holdToken string
     * @param $orderId string
     * @return void
     */
    public function changeObjectStatus($keyOrKeys, $objectOrObjects, $status, $holdToken = null, $orderId = null)
    {
        $request = new \stdClass();
        $request->objects = self::normalizeObjects($objectOrObjects);
        $request->status = $status;
        if ($holdToken !== null) {
            $request->holdToken = $holdToken;
        }
        if ($orderId !== null) {
            $request->orderId = $orderId;
        }
        $request->events = is_array($keyOrKeys) ? $keyOrKeys : [$keyOrKeys];
        $this->client->post(
            '/seasons/actions/change-object-status',
            ['json' => $request]
        );
    }

    /**
     * @param $keyOrKeys string|string[]
     * @param $objectOrObjects mixed
     * @param $holdToken string
     * @param $orderId string
     * @return void
     */
    public function book($keyOrKeys, $objectOrObjects, $holdToken = null, $orderId = null)
    {
        $this::changeObjectStatus($keyOrKeys, $objectOrObjects, "booked", $holdToken, $orderId);
    }

    /**
     * @param $keyOrKeys string|string[]
     * @param $objectOrObjects mixed
     * @param $holdToken string
     * @param $orderId string
     * @return void
     */
    public function release($keyOrKeys, $objectOrObjects, $holdToken = null, $orderId = null)
    {
        $this::changeObjectStatus($keyOrKeys, $objectOrObjects, "free", $holdToken, $orderId);
    }

    /**
     * @param $keyOrKeys string|string[]
     * @param $objectOrObjects mixed
     * @param $holdToken string
     * @param $orderId string
     * @return void
     */
    public function hold($keyOrKeys, $objectOrObjects, $holdToken, $orderId = null)
    {
        $this::changeObjectStatus($keyOrKeys, $objectOrObjects, "reservedByToken", $holdToken, $orderId);
    }

    /**
     * @param $key string
     * @param $number int
     * @param $status string
     * @param $categories string[]
     * @param $useObjectUuidsInsteadOfLabels boolean
     * @param $holdToken string
     * @param $orderId string
     * @return BestAvailableObjects
     */
    public function changeBestAvailableObjectStatus($key, $number, $status, $categories = null, $useObjectUuidsInsteadOfLabels = null, $holdToken = null, $orderId = null)
    {
        $request = new \stdClass();
        $bestAvailable = new \stdClass();
        $bestAvailable->number = $number;
        if ($categories !== null) {
            $bestAvailable->categories = $categories;
        }
        if ($useObjectUuidsInsteadOfLabels !== null) {
            $bestAvailable->useObjectUuidsInsteadOfLabels = $useObjectUuidsInsteadOfLabels;
        }
        $request->bestAvailable = $bestAvailable;
        $request->status = $status;
        if ($holdToken !== null) {
            $request->holdToken = $holdToken;
        }
        if ($orderId !== null) {
            $request->orderId = $orderId;
        }
        $res = $this->client->post(
            '/events/' . $key . '/actions/change-object-status',
            ['json' => $request]
        );
        $json = \GuzzleHttp\json_decode($res->getBody());
        $mapper = SeatsioJsonMapper::create();
        return $mapper->map($json, new BestAvailableObjects());
    }

    private static function normalizeObjects($objectOrObjects)
    {
        if (is_array($objectOrObjects)) {
            if (count($objectOrObjects) === 0) {
                return [];
            }
            if ($objectOrObjects[0] instanceof SeatsioObject) {
                return $objectOrObjects;
            }
            if (is_string($objectOrObjects[0])) {
                return array_map(function ($object) {
                    return ["objectId" => $object];
                }, $objectOrObjects);
            }
            return $objectOrObjects;
        }
        return self::normalizeObjects([$objectOrObjects]);
    }

    /**
     * @param $key string
     * @return array
     */
    public function reportByStatus($key)
    {
        $res = $this->client->get('/reports/events/' . $key . '/byStatus');
        $json = \GuzzleHttp\json_decode($res->getBody());
        return $this->mapMultiValuedReport($json);
    }

    /**
     * @param $key string
     * @return array
     */
    public function reportByCategoryLabel($key)
    {
        $res = $this->client->get('/reports/events/' . $key . '/byCategoryLabel');
        $json = \GuzzleHttp\json_decode($res->getBody());
        return $this->mapMultiValuedReport($json);
    }

    /**
     * @param $key string
     * @return array
     */
    public function reportByCategoryKey($key)
    {
        $res = $this->client->get('/reports/events/' . $key . '/byCategoryKey');
        $json = \GuzzleHttp\json_decode($res->getBody());
        return $this->mapMultiValuedReport($json);
    }

    /**
     * @param $key string
     * @return array
     */
    public function reportByLabel($key)
    {
        $res = $this->client->get('/reports/events/' . $key . '/byLabel');
        $json = \GuzzleHttp\json_decode($res->getBody());
        return $this->mapMultiValuedReport($json);
    }

    /**
     * @param $key string
     * @return array
     */
    public function reportByUuid($key)
    {
        $res = $this->client->get('/reports/events/' . $key . '/byUuid');
        $json = \GuzzleHttp\json_decode($res->getBody());
        return $this->mapSingleValuedReport($json);
    }

    /**
     * @param $key string
     * @return array
     */
    public function reportByOrderId($key)
    {
        $res = $this->client->get('/reports/events/' . $key . '/byOrderId');
        $json = \GuzzleHttp\json_decode($res->getBody());
        return $this->mapMultiValuedReport($json);
    }

    /**
     * @param $key string
     * @return array
     */
    public function reportBySection($key)
    {
        $res = $this->client->get('/reports/events/' . $key . '/bySection');
        $json = \GuzzleHttp\json_decode($res->getBody());
        return $this->mapMultiValuedReport($json);
    }

    /**
     * @param $key string
     * @return array
     */
    private static function mapMultiValuedReport($json)
    {
        $mapper = SeatsioJsonMapper::create();
        $result = [];
        foreach ($json as $status => $reportItems) {
            $result[$status] = $mapper->mapArray($reportItems, array(), EventReportItem::class);
        }
        return $result;
    }

    /**
     * @param $key string
     * @return array
     */
    private static function mapSingleValuedReport($json)
    {
        $mapper = SeatsioJsonMapper::create();
        $result = [];
        foreach ($json as $status => $reportItem) {
            $result[$status] = $mapper->map($reportItem, new EventReportItem());
        }
        return $result;
    }

}