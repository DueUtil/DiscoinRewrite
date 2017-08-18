<?php
/**
* Crappy DB connection
* 
* @author MacDue
*/

namespace MacDue\DB;

require_once __DIR__."/../auth.php";
require_once __DIR__."/util.php";

// Connect to the database.
$manager = new \MongoDB\Driver\Manager("mongodb://$user:$pwd@$host/admin?authMechanism=SCRAM-SHA-1");
define("DATABASE", "discoin");



/**
* Gets stuff from a collection
*
* @param string $collection The name of the collection
*
* @author MacDue
*/
function get_collection_data($collection, $query_array=array()) {
    global $manager;
    $query = new \MongoDB\Driver\Query($query_array);
    $cursor = $manager->executeQuery(DATABASE.".$collection",$query);
    return $cursor->toArray();
}


/**
* Upsert into a collection.
*
* @param string $collection The name of the collection
* @param string $_id The ID of the item
* @param mixed $data The data
* @param string $set_mode The mode of setting (default $set)
*
* @author MacDue
*/
function upsert($collection, $_id, $data, $set_mode='$set') {
    global $manager;
    $bulk = new \MongoDB\Driver\BulkWrite;
    $bulk->update(['_id' => $_id], ['$set' => $data], ['upsert' => true]);
    $write_concern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 1000);
    $result = $manager->executeBulkWrite(DATABASE.".$collection", $bulk, $write_concern);
}


/**
* Delete from a collection.
*
* @param string $_id The ID of the item
* @param string $collection The name of the collection
* @param int $limit The max amount of items to delete
*
* @author MacDue
*/
function delete_document($_id, $collection, $limit=1) {
    global $manager;
    $bulk = new \MongoDB\Driver\BulkWrite;
    $bulk->delete(['_id' => $_id], ['limit' => $limit]);
    $result = $manager->executeBulkWrite(DATABASE.".$collection", $bulk);
}

?>
