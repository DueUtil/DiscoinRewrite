<?php
// Temp script to update objects to BSON

require_once __DIR__."/bots.php";
require_once __DIR__."/transactions.php";
require_once __DIR__."/users.php";
require_once __DIR__."/../scripts/dbconn.php";

\MacDue\DB\delete_document("bots", "132315148487622656/mongoiswank2");
\MacDue\DB\delete_document("bots", "132315148487622656/MongoIsWank2");

foreach (\Discoin\Bots\get_bots() as $bot) {
    \Discoin\Bots\Bot::convert($bot);
}

foreach (\MacDue\DB\get_collection_data("users") as $users) {
    \Discoin\Users\User::convert($users);
}

foreach (\MacDue\DB\get_collection_data("transactions") as $trans) {
    \Discoin\Transactions\Transaction::convert($trans);
}
?>
