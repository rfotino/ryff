<?php

/**
 * @class UserFeed
 * ===============
 * 
 * Provides static functions for getting a feed of users.
 * 
 * Ryff API <http://www.github.com/rfotino/ryff-api>
 * Released under the Apache License 2.0.
 */
class UserFeed {
    /**
     * Helper function that gets an array of users from a mysqli_result object.
     * 
     * @param mysqli_result $query_results
     * @return array|null An array of User objects or null on failure.
     */
    protected static function get_user_array($query_results) {
        if ($query_results) {
            $users = array();
            while ($row = $query_results->fetch_assoc()) {
                $users[] = User::create($row);
            }
            return $users;
        }
        return null;
    }
    
    /**
     * Helper function that gets a timestamp that represents one day ago, or
     * one week ago, etc.
     * 
     * @param string $time One of "day", "week", "month", or "all".
     * @return string The timestamp.
     */
    protected static function get_from_date($time) {
        switch ($time) {
            case "day":
                $from_time = time() - (60 * 60 * 24);
                break;
            case "week":
                $from_time = time() - (60 * 60 * 24 * 7);
                break;
            case "month":
                $from_time = time() - (60 * 60 * 24 * 30);
                break;
            case "all":
            default:
                $from_time = 0;
                break;
        }
        return date("Y-m-d H:i:s", $from_time);
    }
    
    /**
     * Returns an array of User objects sorted by proximity to the given user.
     * The users can optionally match an array of tags.
     * 
     * @global mysqli $db
     * @global User $CURRENT_USER
     * @param Point $location The latitude and longitude coordinates that are
     *                        being queried.
     * @param array $tags [optional] Tags that the returned users should match.
     * @param int $page [optional] The page number of results, defaults to 1.
     * @param int $limit [optional] The number of results per page, defaults to 15.
     * @param int $user_id [optional] Defaults to the current user.
     * @return array|null An array of User objects, or null on failure.
     */
    public static function search_nearby(Point $location, $tags = array(),
            $page = 1, $limit = 15, $user_id = null) {
        global $db, $CURRENT_USER;
        
        if ($user_id === null && $CURRENT_USER) {
            $user_id = $CURRENT_USER->id;
        }
        
        $safe_tags = array_map(function($tag) use ($db) {
            return "'".$db->real_escape_string($tag)."'";
        }, $tags);
        
        $query = "
            SELECT DISTINCT(u.`user_id`), u.`name`, u.`username`, u.`email`, u.`bio`, u.`date_created`,
            SQRT(POW(X(l.`location`)-".$db->real_escape_string($location->x).",2)+
            POW(Y(l.`location`)-".$db->real_escape_string($location->y).",2)) AS `distance`
            FROM `users` AS u
            ".($tags ? "JOIN `user_tags` AS t
            ON t.`user_id` = u.`user_id`" : "")."
            JOIN `locations` AS l
            ON l.`user_id` = u.`user_id`
            WHERE l.`date_created`=(
                SELECT MAX(l2.`date_created`) 
                FROM `locations` AS l2 
                WHERE l2.`user_id`= l.`user_id`
            )
            ".($tags ? "AND t.`tag` IN (".implode(',', $safe_tags).")" : "")."
            AND l.`user_id`!=".$db->real_escape_string($CURRENT_USER->id)."
            ORDER BY `distance` ASC
            LIMIT ".(((int)$page - 1) * (int)$limit).", ".((int)$limit);
        $results = $db->query($query);
        
        return UserFeed::get_user_array($results);
    }
    
    /**
     * Gets User objects with the most karma in the given time frame,
     * optionally matching the given tags.
     * 
     * @global mysqli $db
     * @param string $time [optional] "day", "week" (default), "month", or "all".
     * @param array $tags [optional]
     * @param int $page [optional] The page number of results, defaults to 1.
     * @param int $limit [optional] The number of results per page defaults to 15.
     * @return array|null An array of User objects, or null on failure.
     */
    public static function search_trending($time = "week", $tags = array(), $page = 1, $limit = 15) {
        global $db;
        
        $safe_tags = array_map(function($tag) use ($db) {
            return "'".$db->real_escape_string($tag)."'";
        }, $tags);
        $from_date = UserFeed::get_from_date($time);
        
        $query = "
            SELECT DISTINCT(u.`user_id`), u.`name`, u.`username`,
                u.`email`, u.`bio`, u.`date_created`,
                COUNT(up.`upvote_id`) AS `num_upvotes`
            FROM `users` AS u
            ".($tags ? "JOIN `user_tags` AS t
            ON t.`user_id` = u.`user_id`" : "")."
            JOIN `posts` AS p
            ON p.`user_id` = u.`user_id`
            JOIN `upvotes` AS up
            ON up.`post_id` = p.`post_id`
            WHERE up.`date_created` >= '".$db->real_escape_string($from_date)."'
            ".($tags ? "AND t.`tag` IN (".implode(',', $safe_tags).")" : "")."
            ORDER BY `num_upvotes` DESC
            LIMIT ".(((int)$page - 1) * (int)$limit).", ".((int)$limit);
        $results = $db->query($query);
        
        return UserFeed::get_user_array($results);
    }
}
