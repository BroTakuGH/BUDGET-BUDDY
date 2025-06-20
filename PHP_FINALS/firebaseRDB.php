<?php
/*
 * class name: firebaseRDB
 * version: 1.1
 * author: Devisty (modified by ChatGPT)
 */

class firebaseRDB {
    private $url;

    function __construct($url = null) {
        if (isset($url)) {
            $this->url = rtrim($url, '/');
        } else {
            throw new Exception("Database URL must be specified");
        }
    }

    private function grab($url, $method, $par = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (isset($par)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $par);
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    public function insert($table, $data) {
        $path = $this->url . "/$table.json";
        $grab = $this->grab($path, "POST", json_encode($data));
        return $grab;
    }

    public function insertWithIncrement($table, $data) {
        $uniqueID = $this->getNextID("counters/$table");
        $path = $this->url . "/$table/$uniqueID.json";
        $grab = $this->grab($path, "PUT", json_encode($data));
        return $grab;
    }

    public function update($table, $uniqueID, $data) {
        $path = $this->url . "/$table/$uniqueID.json";
        $grab = $this->grab($path, "PATCH", json_encode($data));
        return $grab;
    }

    public function delete($table, $uniqueID) {
        $path = $this->url . "/$table/$uniqueID.json";
        $grab = $this->grab($path, "DELETE");
        return $grab;
    }

    public function retrieve($dbPath, $queryKey = null, $queryType = null, $queryVal = null) {
        if (isset($queryType) && isset($queryKey) && isset($queryVal)) {
            $queryVal = urlencode($queryVal);
            if ($queryType == "EQUAL") {
                $pars = "orderBy=\"$queryKey\"&equalTo=\"$queryVal\"";
            } elseif ($queryType == "LIKE") {
                $pars = "orderBy=\"$queryKey\"&startAt=\"$queryVal\"";
            }
        }
        $pars = isset($pars) ? "?$pars" : "";
        $path = $this->url . "/$dbPath.json$pars";
        $grab = $this->grab($path, "GET");
        return $grab;
    }

    private function getNextID($counterPath) {
        $path = $this->url . "/$counterPath.json";
        $current = $this->grab($path, "GET");
        $current = json_decode($current, true);

        $nextID = isset($current) ? $current + 1 : 1;

        // Store the new counter
        $this->grab($path, "PUT", $nextID);

        return (string)$nextID;
    }
}
?>