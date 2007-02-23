<?php

/*
+---------------------------------------------------------------------------+
| Max Media Manager v0.3                                                    |
| =================                                                         |
|                                                                           |
| Copyright (c) 2003-2006 m3 Media Services Limited                         |
| For contact details, see: http://www.m3.net/                              |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

require_once MAX_PATH . '/lib/max/OperationInterval.php';
require_once MAX_PATH . '/lib/max/core/ServiceLocator.php';
require_once MAX_PATH . '/lib/max/Dal/Maintenance/Common.php';
require_once 'Date.php';

/**
 * Definition of how far back in time the DAL will look for
 * data relating to when an ad last delivered.
 */
define('MAX_PREVIOUS_AD_DELIVERY_INFO_LIMIT', MINUTES_PER_WEEK);

/**
 * Definitions for different types of Maintenance Priority process
 * log information.
 */
define('DAL_PRIORITY_UPDATE_ZIF',                   0);
define('DAL_PRIORITY_UPDATE_PRIORITY_COMPENSATION', 1);

/**
 * The non-DB specific Data Access Layer (DAL) class for the Maintenance Priority Engine.
 *
 * @package    MaxDal
 * @subpackage MaintenancePriority
 * @author     James Floyd <james@m3.net>
 * @author     Andrew Hill <andrew@m3.net>
 * @author     Radek Maciaszek <radek@m3.net>
 */
class MAX_Dal_Maintenance_Priority extends MAX_Dal_Maintenance_Common
{

    /**
     * The class constructor method.
     */
    function MAX_Dal_Maintenance_Priority()
    {
        parent::MAX_Dal_Maintenance_Common();
    }

    /**
     * A method to store details on the last time that the maintenance priority
     * process ran.
     *
     * @param Date $oStart The time that the maintenance priority run started.
     * @param Date $oEnd The time that the maintenance priority run ended.
     * @param Date $oUpdateTo The end of the last operation interval ID that
     *                        has been updated. NULL is permitted, in the
     *                        case that this information does not actually
     *                        apply, should only the start/end dates of the
     *                        process run be relevant.
     * @param integer $type The type of priority run performed.
     */
    function setMaintenancePriorityLastRunInfo($oStart, $oEnd, $oUpdateTo, $type)
    {
        return $this->setProcessLastRunInfo($oStart, $oEnd, $oUpdateTo, 'log_maintenance_priority', true, 'run_type', $type);
    }

    /**
     * A method to return the time that the maintenance priority scripts
     * were last run, and the operation interval that was in use at the time.
     *
     * @param integer $type Optional integer to limit the returned result to having
     *                      a specific "run_type" value.
     * @param array  $aAdditionalFields An array of strings, representing any additional
     *                                  data fields to return, along with the default
     *                                  'updated_to' and 'operation_interval' fields.
     * @return mixed False on error, null no no result, otherwise, an array containing the
     *               'updated_to' and 'operation_interval' fields, which represent the time
     *               that the Maintenance Priority process last completed updating data until
     *               and the Operation Interval length at that time, as well as any additional
     *               fields (see $aAdditionalFields parameter).
     */
    function getMaintenancePriorityLastRunInfo($type = null, $aAdditionalFields = array())
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        $table = $conf['table']['prefix'] .
                 $conf['table']['log_maintenance_priority'];
        $aFields = array('operation_interval');
        if (is_array($aAdditionalFields) && !empty($aAdditionalFields)) {
            $aFields = array_merge($aFields, $aAdditionalFields);
        }
        if (!is_null($type)) {
            if ($type == DAL_PRIORITY_UPDATE_ZIF) {
                $whereClause = 'WHERE (run_type = ' . DAL_PRIORITY_UPDATE_ZIF . ')';
            } elseif ($type == DAL_PRIORITY_UPDATE_PRIORITY_COMPENSATION) {
                $whereClause = 'WHERE (run_type = ' . DAL_PRIORITY_UPDATE_PRIORITY_COMPENSATION . ')';
            } else {
                MAX::debug('Invalid run_type value ' . $type, PEAR_LOG_ERR);
                MAX::debug('Aborting script execution', PEAR_LOG_ERR);
                exit();
            }
        }
        return $this->getProcessLastRunInfo($table, $aFields, $whereClause);
    }

    /**
     * A method to get all placements (used to be campaigns).
     *
     * @param $fields   array An array of extra fields to select, if required.
     * @param $where    array An array of extra where statements to filter on, if required.
     *                        This would almost always be set, to limit the placements returned
     *                        to some sub-set of all the placements.
     * @param $joins    array An array of extra join statements to join with, if required.
     * @param $orderBy  array An array of extra order by statements to order with, if required.
     * @return array
     */
    function getPlacements($fields = array(), $wheres = array(), $joins = array(), $orderBys = array())
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        $query = array();
        $table             = $conf['table']['prefix'] . $conf['table']['campaigns'];
        $query['table']    = $table;
        $query['fields']   = array(
                                "$table.campaignid",
                                "$table.views",
                                "$table.clicks",
                                "$table.conversions",
                                "$table.expire",
                                "$table.target_impression",
                                "$table.target_click",
                                "$table.target_conversion",
                                "$table.priority"
                             );
        // Add the custom fields
        if (is_array($fields) && (count($fields) > 0)) {
            foreach ($fields as $field) {
                $query['fields'][] = "$field";
            }
        }
        // Add the custom where statements
        if (is_array($wheres) && (count($wheres) > 0)) {
            foreach ($wheres as $where) {
                $query['wheres'][] = $where;
            }
        }
        // Add the custom join statements
        if (is_array($joins) && (count($joins) > 0)) {
            foreach ($joins as $join) {
                $query['joins'][] = $join;
            }
        }
        // Add the custom order by statements
        if (is_array($orderBys) && (count($orderBys) > 0)) {
            foreach ($orderBys as $orderBy) {
                $query['orderBys'][] = $orderBy;
            }
        }
        return $this->_get($query);
    }

    /**
     * A method to get basic data about placements (previously campaigns).
     *
     * @param integer $id The placement ID.
     * @return array An array containing the advertiser_id, placement_id,
     *               and name of the placement, if the placement is "active"
     *               or not (true/false), and the "num_children" value (the
     *               number of advertisements in the placement).
     */
    function getPlacementData($id)
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        $query = array();
        $table             = $conf['table']['prefix'] . $conf['table']['campaigns'];
        $joinTable         = $conf['table']['prefix'] . $conf['table']['banners'];
        $query['table']    = $table;
        $query['fields']   = array(
                                "$table.clientid AS advertiser_id",
                                "$table.campaignid AS placement_id",
                                "$table.campaignname AS name",
                                "$table.active AS active",
                                "COUNT($joinTable.bannerid) AS num_children"
                             );
        $query['wheres']   = array(
                                array("$table.campaignid = $id", 'AND')
                             );
        $query['joins']    = array(
                                array($joinTable, "$table.campaignid = $joinTable.campaignid")
                             );
        $query['group']    = "advertiser_id, placement_id, name, active";
        return $this->_get($query);
    }

    /**
     * A method to get the details of the total number of advertisements
     * delivered, to date, for a specified placement.
     *
     * @param integer $id The placement ID.
     * @return array An array of arrays, with each containing the "placement_id",
     *               "sum_requests", "sum_views", "sum_clicks" and "sum_conversions"
     *               for that placement.
     */
    function getPlacementDeliveryToDate($id)
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        $query = array();
        $table             = $conf['table']['prefix'] . $conf['table']['campaigns'];
        $joinTable1        = $conf['table']['prefix'] . $conf['table']['banners'];
        $joinTable2        = $conf['table']['prefix'] . $conf['table']['data_intermediate_ad'];
        $query['table']    = $table;
        $query['fields']   = array(
                                "SUM($joinTable2.requests) AS sum_requests",
                                "SUM($joinTable2.impressions) AS sum_views",
                                "SUM($joinTable2.clicks) AS sum_clicks",
                                "SUM($joinTable2.conversions) AS sum_conversions",
                                "$table.campaignid AS placement_id"
                             );
        $query['wheres']   = array(
                                array("$table.campaignid = $id", 'AND')
                             );
        $query['joins']    = array(
                                array($joinTable1, "$table.campaignid = $joinTable1.campaignid"),
                                array($joinTable2, "$joinTable1.bannerid = $joinTable2.ad_id")
                             );
        $query['group']    = "placement_id";
        return $this->_get($query);
    }

    /**
     * A method to get the details of the total number of advertisements
     * delivered, today, for a specified placement.
     *
     * @param integer $id The placement ID.
     * @param string $today A "CCYY-MM-DD" formatted representation of today's date.
     * @return array An array of arrays, with each containing the "placement_id",
     *               "sum_requests", "sum_views", "sum_clicks" and "sum_conversions"
     *               for that placement.
     */
    function getPlacementDeliveryToday($id, $today)
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        $query = array();
        $table             = $conf['table']['prefix'] . $conf['table']['campaigns'];
        $joinTable1        = $conf['table']['prefix'] . $conf['table']['banners'];
        $joinTable2        = $conf['table']['prefix'] . $conf['table']['data_intermediate_ad'];
        $query['table']    = $table;
        $query['fields']   = array(
                                "SUM($joinTable2.requests) AS sum_requests",
                                "SUM($joinTable2.impressions) AS sum_views",
                                "SUM($joinTable2.clicks) AS sum_clicks",
                                "SUM($joinTable2.conversions) AS sum_conversions",
                                "$table.campaignid AS placement_id"
                             );
        $query['wheres']   = array(
                                array("$table.campaignid = $id", 'AND')
                             );
        $query['joins']    = array(
                                array($joinTable1, "$table.campaignid = $joinTable1.campaignid"),
                                array($joinTable2, "$joinTable1.bannerid = $joinTable2.ad_id AND $joinTable2.day = '$today'")
                             );
        $query['group']    = "placement_id";
        return $this->_get($query);
    }

    /**
     * A convenience method that combines the results of the getPlacementData and
     * (getPlacementDeliveryToDate or getPlacementDeliverToday) methods into one
     * result set.
     *
     * @param integer $id The placement ID.
     * @param boolean $todayOnly If true, only look at what has been delivered today
     *                           for the delivery statistics (ie. use getPlacementDeliverToday),
     *                           otherwise use the delivery statistics for the entire placement
     *                           lifetime (ie. use getPlacementDeliveryToDate).
     * @param string $today A "CCYY-MM-DD" formatted representation of today's date, only
     *                      required when $todayOnly is true.
     * @return array An array of the placement's details, containing the following:
     *
     * Array
     *     (
     *         [advertiser_id]   => 1
     *         [placement_id]    => 1
     *         [name]            => Placement name
     *         [active]          => t
     *         [num_children]    => 2
     *         [sum_requests]    => 0
     *         [sum_views]       => 0
     *         [sum_clicks]      => 0
     *         [sum_conversions] => 0
     *     )
     */
    function getPlacementStats($id, $todayOnly = false, $today = '')
    {
        $aTemp = $this->getPlacementData($id);
        if (is_array($aTemp[0]) && (count($aTemp[0]) > 0)) {
            $aPlacements = $aTemp[0];
        }
        if ($todayOnly) {
            $aPlacementsStats = $this->getPlacementDeliveryToday($id, $today);
        } else {
            $aPlacementsStats = $this->getPlacementDeliveryToDate($id);
        }
        $items = array(
            'sum_requests',
            'sum_views',
            'sum_clicks',
            'sum_conversions'
        );
        foreach ($items as $item) {
            if (!empty($aPlacementsStats[0][$item])) {
                $aPlacements[$item] = $aPlacementsStats[0][$item] ;
            } else {
                $aPlacements[$item] = 0;
            }
        }
        return $aPlacements;
    }

    /**
     * A method to get all zones that have had advertisements allocated to their
     * impression inventory.
     *
     * @access public
     * @return mixed An array of arrays, each one representing a zone/ad pair
     *               found, in zone ID, ad ID order.
     */
    function &getAllZonesWithAllocInv()
    {
        MAX::debug('Getting all of the zones with ad impressions allocated.', PEAR_LOG_DEBUG);
        $conf = $GLOBALS['_MAX']['CONF'];
        $query = array();
        $table             = 'tmp_ad_zone_impression';
        $query['table']    = $table;
        $query['fields']   = array(
                                "$table.zone_id",
                                "$table.ad_id",
                                "$table.required_impressions",
                                "$table.requested_impressions"
                             );
        $query['orderBys'] = array(
                                array("$table.zone_id", 'ASC'),
                                array("$table.ad_id", 'ASC')
                             );
        return $this->_get($query);
    }

    /**
     * A method to get the available impression inventory for all zones
     * for the current operation interval, and the actual impressions
     * for the previous operation interval.
     *
     * Requires that a current day/time (as a Date object) be registered
     * with the ServiceLocator (as "now").
     *
     * Ignores the special zone with zone ID of 0 (zero), as this is a
     * reserved zone for storing statistics about directly selected ads,
     * and as a result, shouldn't be used during prioritisation.
     *
     * @access public
     * @return mixed An array of arrays, each one representing a zone found,
     *               in zone ID order, or false if no Date object registered
     *               with the ServiceLocator.
     */
    function &getAllZonesImpInv()
    {
        MAX::debug("Getting all of the zones impression inventories.", PEAR_LOG_DEBUG);
        $conf = $GLOBALS['_MAX']['CONF'];
        $oServiceLocator = &ServiceLocator::instance();
        $oDate = &$oServiceLocator->get('now');
        if (!$oDate) {
            return false;
        }
        // Get the zone impression forecasts for the current operation interval, and the actual zone
        // impressions for the previous operation interval, where they exist
        $currentOpIntID = MAX_OperationInterval::convertDateToOperationIntervalID($oDate);
        $aCurrentDates = MAX_OperationInterval::convertDateToOperationIntervalStartAndEndDates($oDate);
        $previousOptIntID = MAX_OperationInterval::previousOperationIntervalID($currentOpIntID);
        $aPreviousDates = MAX_OperationInterval::convertDateToPreviousOperationIntervalStartAndEndDates($oDate);
        $table = $conf['table']['prefix'] . $conf['table']['data_summary_zone_impression_history'];
        $query = "
            SELECT
                t1.zone_id AS zone_id,
                t1.forecast_impressions AS forecast_impressions,
                t2.actual_impressions AS actual_impressions
            FROM
                $table AS t1
            LEFT JOIN
                $table AS t2
            ON
                t1.zone_id = t2.zone_id
                AND t2.operation_interval = {$conf['maintenance']['operationInterval']}
                AND t2.operation_interval_id = $previousOptIntID
                AND t2.interval_start = '" . $aPreviousDates['start']->format('%Y-%m-%d %H:%M:%S') . "'
                AND t2.interval_end = '" . $aPreviousDates['end']->format('%Y-%m-%d %H:%M:%S') . "'
                AND t2.zone_id != 0
            WHERE
                t1.operation_interval = {$conf['maintenance']['operationInterval']}
                AND t1.operation_interval_id = $currentOpIntID
                AND t1.interval_start = '" . $aCurrentDates['start']->format('%Y-%m-%d %H:%M:%S') . "'
                AND t1.interval_end = '" . $aCurrentDates['end']->format('%Y-%m-%d %H:%M:%S') . "'
                AND t1.zone_id != 0
            ORDER BY
                t1.zone_id";
        $result = $this->dbh->getAll($query);
        foreach ($result as $row) {
            $aResult[$row['zone_id']] = $row;
        }
        // Get all possible zones in the system
        $query = "
            SELECT
                zoneid AS zone_id
            FROM
                {$conf['table']['prefix']}{$conf['table']['zones']}
            WHERE
                zoneid != 0";
        $result = $this->dbh->getAll($query);
        foreach ($result as $row) {
            if (!isset($aResult[$row['zone_id']])) {
                $aResult[$row['zone_id']] = array(
                    'zone_id'               => $row['zone_id'],
                    'forecast_impressions'  => $conf['priority']['defaultZoneForecastImpressions'],
                    'actual_impressions'    => 0
                );
            }
        }
        return $aResult;
    }

    /**
     * A method to get all active advertisements where the delivery limitations of
     * the ads have had changes made:
     *  - In the current operation interval; or
     *  - In the previous operation interver, but after last time the Priority
     *    Compensation process started running.
     *
     * @param array An array containing the last time that the Priority Compensation
     *              process started running in the 'start_run' field (as a PEAR::Date),
     *              and the current date/time (ie. a date/time in the current
     *              operation interval) in the 'now' field (as a PEAR::Date).
     * @return array An array, indexed by ad ID, of the date/times (as strings)
     *               that the delivery limitations were altered, of those active
     *               ads that had delivery limitations changed since the given
     *               'start_run' date.
     */
    function &getAllDeliveryLimitationChangedAds($aLastRun)
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        MAX::debug("Getting all ads where delivery limitations have changed.", PEAR_LOG_DEBUG);
        $aAds = array();
        // Test the input data
        if (!is_array($aLastRun) || (count($aLastRun) != 2)) {
            return $aAds;
        }
        if (is_null($aLastRun['start_run']) || (!is_a($aLastRun['start_run'], 'Date'))) {
            return $aAds;
        }
        if (is_null($aLastRun['now']) || (!is_a($aLastRun['now'], 'Date'))) {
            return $aAds;
        }
        // Select those ads where the delivery limitations were changed in the current
        // operation interval
        $aDates = MAX_OperationInterval::convertDateToOperationIntervalStartAndEndDates($aLastRun['now']);
        $query = "
            SELECT
                b.bannerid AS ad_id,
                b.acls_updated AS changed
            FROM
                {$conf['table']['prefix']}{$conf['table']['banners']} AS b,
                {$conf['table']['prefix']}{$conf['table']['campaigns']} AS c
            WHERE
                b.acls_updated >= '" . $aDates['start']->format('%Y-%m-%d %H:%M:%S') . "'
                AND b.acls_updated <= '" . $aDates['end']->format('%Y-%m-%d %H:%M:%S') . "'
                AND b.active = 't'
                AND b.campaignid = c.campaignid
                AND c.active = 't'";
        $aResult = $this->dbh->getAll($query);
        foreach ($aResult as $aRow) {
            $aAds[$aRow['ad_id']] = $aRow['changed'];
        }
        // Select those ads where the delivery limitations were changed in the previous
        // operation interval, but after the last time Priority Compensation started to run
        $oDate = new Date();
        $oDate->copy($aDates['start']);
        $oDate->subtractSeconds(1);
        $query = "
            SELECT
                b.bannerid AS ad_id,
                b.acls_updated AS changed
            FROM
                {$conf['table']['prefix']}{$conf['table']['banners']} AS b,
                {$conf['table']['prefix']}{$conf['table']['campaigns']} AS c
            WHERE
                b.acls_updated >= '" . $aLastRun['start_run']->format('%Y-%m-%d %H:%M:%S') . "'
                AND b.acls_updated <= '" . $oDate->format('%Y-%m-%d %H:%M:%S') . "'
                AND b.active = 't'
                AND b.campaignid = c.campaignid
                AND c.active = 't'";
        $aResult = $this->dbh->getAll($query);
        foreach ($aResult as $aRow) {
            $aAds[$aRow['ad_id']] = $aRow['changed'];
        }
        return $aAds;
    }

    /**
     * A method to get the most recent details of advertisement delivery for
     * a given list of advertisement/zone pairs set to deliver in the current
     * operation interval. Normally, the data will be retrieved from the previous
     * operation interval, but if no data exists for that ad/zone pair, then the
     * next previous operation interval will be tried, and so on, up to a limit
     * of MAX_PREVIOUS_AD_DELIVERY_INFO_LIMIT minutes. (The default is one week.)
     *
     * Requires that a current day/time (as a Date object) be registered
     * with the ServiceLocator (as "now").
     *
     * Note: The logic of this method seems a little convoluted, and it is.
     * However, it needs to be. The reason being:
     *  - If an ad delivered in the previous operation interval, it should
     *    have a priority set in ad_zone_assoc. This should be the most
     *    recent entry in data_summary_ad_zone_assoc. So, the first step is
     *    to get the data for all ads that have delivered in the previous
     *    OI, and the associated prioritisation data.
     *  - If an ad did not deliver, the prioritisation data set in the
     *    previous OI is still needed, so the second step is to get those
     *    ads that had prioiritisation data set in the previous OI, but did
     *    not deliver.
     *  - Finally, as some ads are limited by hour (for example), we want to
     *    to be able to get past prioritisation data for ads that were
     *    disabled in the last OI, so, we need to look for ad/zone pairs
     *    that have not yet been found, and get BOTH the prioritisation and
     *    delivery data from the last OI when the ads were active in the
     *    zones.
     * This is why the method uses a complex, 3 step process!
     *
     * @access public
     * @param array $aCurrentZones An array of Zones, indexed by Zone ID, with each
     *                             Zone containing the Advert objects that are linked
     *                             to deliver in the zone, in the current operation
     *                             interval.
     * @return mixed An array of arrays of arrays, each one representing a set of
     *               ad/zone delivery information, indexed by ad ID and zone ID.
     *               Each sub-sub array, if present, has the format:
     *               array (
     *                  'ad_id'                         => integer
     *                  'zone_id'                       => integer
     *                  'required_impressions'          => integer
     *                  'requested_impressions'         => integer
     *                  'priority_factor'               => double
     *                  'past_zone_traffic_fraction'    => double
     *                  'impressions'                   => integer
     *               )
     *               Returns false when the current date/time is not set in the
     *               ServiceLocator.
     */
    function &getPreviousAdDeliveryInfo($aCurrentZones)
    {
        MAX::debug("Getting details of previous ad/zone delivery.", PEAR_LOG_DEBUG);
        $conf = $GLOBALS['_MAX']['CONF'];
        $oServiceLocator = &ServiceLocator::instance();
        $oDate = &$oServiceLocator->get('now');
        if (!$oDate) {
            return false;
        }
        $aAds = array();
        $aZones = array();
        $aZonesAds = array();
        $aPastDeliveryResult = array();
        $aPastPriorityResult = array();
        $aNonDeliveringPastPriorityResult = array();
        $aFinalResult = array();
        // Obtain the earliest existing interval_start date found in the
        // data_summary_ad_zone_assoc table
        $query = "
            SELECT
                interval_start AS interval_start
            FROM
                {$conf['table']['prefix']}{$conf['table']['data_summary_ad_zone_assoc']}
            ORDER BY
                interval_start
            LIMIT
                1";
        $aResult = $this->dbh->getAll($query);
        if ((!PEAR::isError($aResult)) && (count($aResult) == 1)) {
            $oEarliestPastPriorityRecordDate = new Date($aResult[0]['interval_start']);
            // Create a new date that is the limit number of minutes ago
            $oLimitDate = new Date();
            $oLimitDate->copy($oDate);
            $oLimitDate->subtractSeconds(MAX_PREVIOUS_AD_DELIVERY_INFO_LIMIT * 60);
            // Is the earliest date before this date?
            if ($oEarliestPastPriorityRecordDate->before($oLimitDate)) {
                // Use the limit date instead
                $oEarliestPastPriorityRecordDate = new Date();
                $oEarliestPastPriorityRecordDate->copy($oLimitDate);
            }
        }
        // Obtain the operation interval ID, and the start and end dates of the previous
        // operation interval
        $currentOperationIntervalID = MAX_OperationInterval::convertDateToOperationIntervalID($oDate);
        $previousOperationIntervalID = MAX_OperationInterval::previousOperationIntervalID($currentOperationIntervalID);
        $aDates = MAX_OperationInterval::convertDateToPreviousOperationIntervalStartAndEndDates($oDate);
        // Obtain the ad ID, zone ID and number of impressions delivered for every ad/zone
        // combination that delivered impressions in the previous operation interval
        MAX::debug("  - Getting details of ad/zone pairs that delivered last OI.", PEAR_LOG_DEBUG);
        $query = "
            SELECT
                ad_id AS ad_id,
                zone_id AS zone_id,
                SUM(impressions) AS impressions
            FROM
                {$conf['table']['prefix']}{$conf['table']['data_intermediate_ad']}
            WHERE
                operation_interval = {$conf['maintenance']['operationInterval']}
                AND operation_interval_id = $previousOperationIntervalID
                AND interval_start = '" . $aDates['start']->format('%Y-%m-%d %H:%M:%S') . "'
                AND interval_end = '" . $aDates['end']->format('%Y-%m-%d %H:%M:%S') . "'
                AND ad_id != 0
                AND zone_id != 0
            GROUP BY
                ad_id,
                zone_id
            ORDER BY
                ad_id,
                zone_id";
        $aResult = $this->dbh->getAll($query);
        foreach ($aResult as $aRow) {
            // Store the ad ID as being one that has delivered
            $aAds[$aRow['ad_id']] = $aRow['ad_id'];
            // Store the zone ID as one that had impressions in it
            $aZones[$aRow['zone_id']] = $aRow['zone_id'];
            // Store the ad IDs by zone ID
            $aZonesAds[$aRow['zone_id']][$aRow['ad_id']] = true;
            // Store the impressions delivered for the ad/zone pair, and that the past
            // priority information for this ad/zone pair has not been found yet
            $aPastDeliveryResult[$aRow['ad_id']][$aRow['zone_id']]['impressions'] = $aRow['impressions'];
            $aPastDeliveryResult[$aRow['ad_id']][$aRow['zone_id']]['pastPriorityFound'] = false;
        }
        // Did any ads deliver last operation interval?
        if (!empty($aAds)) {
            // Is there past deliver data?
            if (isset($oEarliestPastPriorityRecordDate)) {
                // As all of the above ad/zone combinations delivered impressions in the previous
                // operation interval, a priority value must have existed. However, the priority
                // value may *not* have come from the previous interval, as maintenance may not
                // have been run (although it should have ;-)
                // However, just to be sure, interate backwards over past operation intervals
                // (up to the earliest existing record), obtaining the details of the impressions
                // requested and the priority values used for the above combinations
                $previousOperationIntervalIDLoop = $previousOperationIntervalID;
                $aDatesLoop = array();
                $aDatesLoop['start'] = new Date();
                $aDatesLoop['start']->copy($aDates['start']);
                $aDatesLoop['end'] = new Date();
                $aDatesLoop['end']->copy($aDates['end']);
                $foundAll = false;
                while (!$foundAll) {
                    if (!empty($aAds) && !empty($aZones)) {
                        $query = "
                            SELECT
                                ad_id AS ad_id,
                                zone_id AS zone_id,
                                required_impressions AS required_impressions,
                                requested_impressions AS requested_impressions,
                                priority_factor AS priority_factor,
                                past_zone_traffic_fraction AS past_zone_traffic_fraction,
                                created AS created,
                                expired AS expired
                            FROM
                                {$conf['table']['prefix']}{$conf['table']['data_summary_ad_zone_assoc']}
                            WHERE
                                ad_id IN (" . implode(', ', $aAds) . ")
                                AND zone_id IN (" . implode(', ', $aZones) . ")
                                AND operation_interval = {$conf['maintenance']['operationInterval']}
                                AND operation_interval_id = $previousOperationIntervalIDLoop
                                AND interval_start = '" . $aDatesLoop['start']->format('%Y-%m-%d %H:%M:%S') . "'
                                AND interval_end = '" . $aDatesLoop['end']->format('%Y-%m-%d %H:%M:%S') . "'
                            ORDER BY
                                ad_id,
                                zone_id";
                        $aResult = $this->dbh->getAll($query);
                        // Calculate the past priority results, using $aPastDeliveryResult in the call to
                        // _calculateAveragePastPriorityValues(), so that if this is the second (or greater)
                        // time this has been done then any ad/zone pairs that have come up with details for
                        // a second (or greater) time will NOT have their past priority info re-calculated
                        // with the wrong values
                        MAX::debug("  - Getting past details of ad/zone pairs for OI starting at " .
                                   $aDatesLoop['start']->format('%Y-%m-%d %H:%M:%S') . ".", PEAR_LOG_DEBUG);
                        $this->_calculateAveragePastPriorityValues($aPastPriorityResult, $aResult, $oDate, $aPastDeliveryResult);
                        // Loop over the results, marking off the ad/zone combinations
                        // in the past delivery array as having had their past prioritisation
                        // information found and/or calculated
                        if (!empty($aPastPriorityResult)) {
                            foreach ($aPastPriorityResult as $a => $aAd) {
                                foreach ($aAd as $z => $aZone) {
                                    if ($aZone['pastPriorityFound']) {
                                        $aPastDeliveryResult[$a][$z]['pastPriorityFound'] = true;
                                    }
                                }
                            }
                        }
                    }
                    // Look over the past delivery array to see if there are any ad/zone
                    // combinations that do not yet have the past prioritisation information
                    $foundAll = true;
                    if (!empty($aPastDeliveryResult)) {
                        foreach ($aPastDeliveryResult as $a => $aAd) {
                            $remove = true;
                            foreach ($aAd as $z => $aZone) {
                                if (!$aPastDeliveryResult[$a][$z]['pastPriorityFound']) {
                                    if ($foundAll) {
                                        // Need to go back in time to look for more past priority info
                                        $previousOperationIntervalIDLoop = MAX_OperationInterval::previousOperationIntervalID($previousOperationIntervalIDLoop);
                                        $aDatesLoop = MAX_OperationInterval::convertDateToPreviousOperationIntervalStartAndEndDates($aDatesLoop['start']);
                                        if (!$aDatesLoop['start']->before($oEarliestPastPriorityRecordDate)) {
                                            // Haven't exceeded earliest priority info date, so okay
                                            // to go ahead with going back in time...
                                            $foundAll = false;
                                        }
                                    }
                                    $remove = false;
                                }
                                // Remove the ad from the list of ads to select from next round,
                                // and check if this was the last ad in the zone, and, if so,
                                // also remove the zone from the list of zones to select from
                                // next round
                                if ($remove) {
                                    unset($aAds[$a]);
                                    unset($aZonesAds[$z][$a]);
                                    if (count($aZonesAds[$z]) == 0) {
                                        unset($aZones[$z]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // Merge the past priority and delivery values into the final results array
            if (!empty($aPastDeliveryResult)) {
                foreach ($aPastDeliveryResult as $a => $aAd) {
                    foreach ($aAd as $z => $aZone) {
                        $aFinalResult[$a][$z] = array(
                            'ad_id'                         => $a,
                            'zone_id'                       => $z,
                            'required_impressions'          => $aPastPriorityResult[$a][$z]['required_impressions'],
                            'requested_impressions'         => $aPastPriorityResult[$a][$z]['requested_impressions'],
                            'priority_factor'               => $aPastPriorityResult[$a][$z]['priority_factor'],
                            'past_zone_traffic_fraction'    => $aPastPriorityResult[$a][$z]['past_zone_traffic_fraction'],
                            'impressions'                   => $aPastDeliveryResult[$a][$z]['impressions']
                        );
                    }
                }
            }
        }
        // Select the details of all ad/zones that had required/requested impressions,
        // in the previous operation interval, but for which no impressions were delivered
        MAX::debug("  - Getting details of ad/zone pairs that did not deliver last OI (but should have).", PEAR_LOG_DEBUG);
        $query = "
            SELECT
                dsaza.ad_id AS ad_id,
                dsaza.zone_id AS zone_id,
                dsaza.required_impressions AS required_impressions,
                dsaza.requested_impressions AS requested_impressions,
                dsaza.priority_factor AS priority_factor,
                dsaza.past_zone_traffic_fraction AS past_zone_traffic_fraction,
                dsaza.created AS created,
                dsaza.expired AS expired
            FROM
                {$conf['table']['prefix']}{$conf['table']['data_summary_ad_zone_assoc']} AS dsaza
            LEFT JOIN
                {$conf['table']['prefix']}{$conf['table']['data_intermediate_ad']} AS dia
            ON
                dsaza.ad_id = dia.ad_id
                AND dsaza.zone_id = dia.zone_id
                AND dsaza.operation_interval = dia.operation_interval
                AND dsaza.operation_interval_id = dia.operation_interval_id
                AND dsaza.interval_start = dia.interval_start
                AND dsaza.interval_end = dia.interval_end
            WHERE
                dsaza.operation_interval = {$conf['maintenance']['operationInterval']}
                AND dsaza.operation_interval_id = $previousOperationIntervalID
                AND dsaza.interval_start = '" . $aDates['start']->format('%Y-%m-%d %H:%M:%S') . "'
                AND dsaza.interval_end = '" . $aDates['end']->format('%Y-%m-%d %H:%M:%S') . "'
                AND dsaza.required_impressions > 0
                AND dsaza.requested_impressions > 0
                AND dia.ad_id IS NULL
                AND dia.zone_id IS NULL
            ORDER BY
                ad_id,
                zone_id";
        $aResult = $this->dbh->getAll($query);
        // Calculate the past priority results, but without the optional parameter to
        // _calculateAveragePastPriorityValues(), as this is a single calculation on data
        // in the past OI, and no further looping back over time will occur
        MAX::debug("  - Getting past details of the non-delivering ad/zone pairs", PEAR_LOG_DEBUG);
        $this->_calculateAveragePastPriorityValues($aNonDeliveringPastPriorityResult, $aResult, $oDate);
        // Merge the past priority values into the final results array
        if (!empty($aNonDeliveringPastPriorityResult)) {
            foreach ($aNonDeliveringPastPriorityResult as $a => $aAd) {
                foreach ($aAd as $z => $aZone) {
                    if (empty($aFinalResult[$a][$z])) {
                        $aFinalResult[$a][$z] = array(
                            'ad_id'                         => $a,
                            'zone_id'                       => $z,
                            'required_impressions'          => $aNonDeliveringPastPriorityResult[$a][$z]['required_impressions'],
                            'requested_impressions'         => $aNonDeliveringPastPriorityResult[$a][$z]['requested_impressions'],
                            'priority_factor'               => $aNonDeliveringPastPriorityResult[$a][$z]['priority_factor'],
                            'past_zone_traffic_fraction'    => $aNonDeliveringPastPriorityResult[$a][$z]['past_zone_traffic_fraction']
                        );
                    }
                }
            }
        }
        // Finally, now that all ad/zone combinations that had required/requested impressions
        // in the previous operation interval and delivered/didn't deliver have been dealt
        // with, check to see if there are any ad/zone combinations that are linked to
        // deliver in the current operation interval, which are not covered by the above
        MAX::debug("  - Finding ad/zone pairs set to deliver, but with no past data yet.", PEAR_LOG_DEBUG);
        $aAds = array();
        $aZones = array();
        $aZonesAds = array();
        $aNotInLastOIPastDeliveryResult = array();
        $aNotInLastOIPastPriorityResult = array();
        if (is_array($aCurrentZones) && !empty($aCurrentZones)) {
            foreach ($aCurrentZones as $zoneId => $oZone) {
                if (is_array($oZone->aAdverts) && !empty($oZone->aAdverts)) {
                    foreach ($oZone->aAdverts as $oAdvert) {
                        // Store that the past priority information for this ad/zone pair may
                        // not have yet been found
                        $aNotInLastOIPastDeliveryResult[$oAdvert->id][$zoneId]['pastPriorityFound'] = false;
                    }
                }
            }
        }
        // Remove those ad/zone pairs that have, in fact, already had their past priority
        // information found previously
        if (!empty($aFinalResult)) {
            foreach ($aFinalResult as $aResult) {
                unset($aNotInLastOIPastDeliveryResult[$aResult['ad_id']][$aResult['zone_id']]);
            }
            foreach ($aNotInLastOIPastDeliveryResult as $adKey => $aZones) {
                if (empty($aZones)) {
                    unset($aNotInLastOIPastDeliveryResult[$adKey]);
                }
            }
        }
        // Create the sets of required ads and zones that need their past priority
        // information from older operation intervals found
        if (!empty($aNotInLastOIPastDeliveryResult)) {
            foreach ($aNotInLastOIPastDeliveryResult as $adKey => $aData) {
                // Store the ad ID as being one that needs to deliver
                $aAds[$adKey] = $adKey;
                foreach ($aData as $zoneKey => $value) {
                    // Store the zone ID as one that has an ad that needs to deliver in it
                    $aZones[$zoneKey] = $zoneKey;
                    // Store the ad IDs by zone ID
                    $aZonesAds[$zoneKey][$adKey] = true;
                }
            }
        }
        // Are there any ad/zone pairs that need data?
        if (!empty($aNotInLastOIPastDeliveryResult)) {
            // Is there past delivery data?
            if (isset($oEarliestPastPriorityRecordDate)) {
                // Loop over the previous operation intervals, starting with the one
                // *before* last, and find when (if possible) these ad/zone pairs
                // last *requested* to deliver; it doesn't matter if they did deliver
                // in that operation interval or not
                $previousOperationIntervalIDLoop = MAX_OperationInterval::previousOperationIntervalID($previousOperationIntervalID);
                $aDatesLoop = MAX_OperationInterval::convertDateToPreviousOperationIntervalStartAndEndDates($aDates['start']);
                $foundAll = false;
                while (!$foundAll) {
                    if (!empty($aAds) && !empty($aZones)) {
                        $query = "
                            SELECT
                                ad_id AS ad_id,
                                zone_id AS zone_id,
                                required_impressions AS required_impressions,
                                requested_impressions AS requested_impressions,
                                priority_factor AS priority_factor,
                                past_zone_traffic_fraction AS past_zone_traffic_fraction,
                                created AS created,
                                expired AS expired,
                                operation_interval AS operation_interval,
                                operation_interval_id AS operation_interval_id,
                                interval_start AS interval_start,
                                interval_end AS interval_end
                            FROM
                                {$conf['table']['prefix']}{$conf['table']['data_summary_ad_zone_assoc']}
                            WHERE
                                ad_id IN (" . implode(', ', $aAds) . ")
                                AND zone_id IN (" . implode(', ', $aZones) . ")
                                AND operation_interval = {$conf['maintenance']['operationInterval']}
                                AND operation_interval_id = $previousOperationIntervalIDLoop
                                AND interval_start = '" . $aDatesLoop['start']->format('%Y-%m-%d %H:%M:%S') . "'
                                AND interval_end = '" . $aDatesLoop['end']->format('%Y-%m-%d %H:%M:%S') . "'
                            ORDER BY
                                ad_id,
                                zone_id";
                        $aResult = $this->dbh->getAll($query);
                        // Calculate the past priority results, using $aNotInLastOIPastDeliveryResult in the
                        // call to _calculateAveragePastPriorityValues(), so that if this is the second
                        // (or greater) time this has been done then any ad/zone pairs that have come up
                        // with details for a second (or greater) time will NOT have their past priority info
                        // re-calculated
                        MAX::debug("  - Getting past details of ad/zone pairs which didn't deliver last OI, for OI " .
                                   "starting at " . $aDatesLoop['start']->format('%Y-%m-%d %H:%M:%S') . ".", PEAR_LOG_DEBUG);
                        $this->_calculateAveragePastPriorityValues($aNotInLastOIPastPriorityResult, $aResult, $oDate, $aNotInLastOIPastDeliveryResult);
                        // Loop over the results, marking off the ad/zone combinations
                        // in the past delivery array as having had their past prioritisation
                        // information found and/or calculated
                        if (!empty($aNotInLastOIPastPriorityResult)) {
                            foreach ($aNotInLastOIPastPriorityResult as $a => $aAd) {
                                foreach ($aAd as $z => $aZone) {
                                    if ($aZone['pastPriorityFound']) {
                                        $aNotInLastOIPastDeliveryResult[$a][$z]['pastPriorityFound'] = true;
                                    }
                                }
                            }
                        }
                        // Look over the past delivery array to see if there are any ad/zone
                        // combinations that do not yet have the past prioritisation information
                        $foundAll = true;
                        if (!empty($aNotInLastOIPastDeliveryResult)) {
                            foreach ($aNotInLastOIPastDeliveryResult as $a => $aAd) {
                                $remove = true;
                                foreach ($aAd as $z => $aZone) {
                                    if (!$aNotInLastOIPastDeliveryResult[$a][$z]['pastPriorityFound']) {
                                        if ($foundAll) {
                                            // Need to go back in time to look for more past priority info
                                            $previousOperationIntervalIDLoop = MAX_OperationInterval::previousOperationIntervalID($previousOperationIntervalIDLoop);
                                            $aDatesLoop = MAX_OperationInterval::convertDateToPreviousOperationIntervalStartAndEndDates($aDatesLoop['start']);
                                            if (!$aDatesLoop['start']->before($oEarliestPastPriorityRecordDate)) {
                                                // Haven't exceeded earliest priority info date, so okay
                                                // to go ahead with going back in time...
                                                $foundAll = false;
                                            }
                                        }
                                        $remove = false;
                                    }
                                    // Remove the ad from the list of ads to select from next round,
                                    // and check if this was the last ad in the zone, and, if so,
                                    // also remove the zone from the list of zones to select from
                                    // next round
                                    if ($remove) {
                                        unset($aAds[$a]);
                                        unset($aZonesAds[$z][$a]);
                                        if (count($aZonesAds[$z]) == 0) {
                                            unset($aZones[$z]);
                                        }
                                    }
                                }
                            }
                        }
                    } 
                    else {
                        $foundAll = true;
                    }
                }
            }
            // Now that it is known when (if) the remaining ads last requested to deliver,
            // determine how many impressions were delivered (if any) during the appropriate
            // operation intervals
            if (!empty($aNotInLastOIPastPriorityResult)) {
                foreach ($aNotInLastOIPastPriorityResult as $a => $aAd) {
                    foreach ($aAd as $z => $aZone) {
                        $query = "
                            SELECT
                                SUM(impressions) AS impressions
                            FROM
                                {$conf['table']['prefix']}{$conf['table']['data_intermediate_ad']}
                            WHERE
                                operation_interval = {$aNotInLastOIPastPriorityResult[$a][$z]['operation_interval']}
                                AND operation_interval_id = {$aNotInLastOIPastPriorityResult[$a][$z]['operation_interval_id']}
                                AND interval_start = '{$aNotInLastOIPastPriorityResult[$a][$z]['interval_start']}'
                                AND interval_end = '{$aNotInLastOIPastPriorityResult[$a][$z]['interval_end']}'
                                AND ad_id = $a
                                AND zone_id = $z";
                        $aResult = $this->dbh->getAll($query);
                        if (isset($aResult[0]['impressions'])) {
                            $aNotInLastOIPastPriorityResult[$a][$z]['impressions'] = $aResult[0]['impressions'];
                        }
                    }
                }
            }
            // Merge the past priority and delivery values into the final results array
            if (!empty($aNotInLastOIPastPriorityResult)) {
                foreach ($aNotInLastOIPastPriorityResult as $a => $aAd) {
                    foreach ($aAd as $z => $aZone) {
                        if (empty($aFinalResult[$a][$z])) {
                            $aFinalResult[$a][$z] = array(
                                'ad_id'                         => $a,
                                'zone_id'                       => $z,
                                'required_impressions'          => $aNotInLastOIPastPriorityResult[$a][$z]['required_impressions'],
                                'requested_impressions'         => $aNotInLastOIPastPriorityResult[$a][$z]['requested_impressions'],
                                'priority_factor'               => $aNotInLastOIPastPriorityResult[$a][$z]['priority_factor'],
                                'past_zone_traffic_fraction'    => $aNotInLastOIPastPriorityResult[$a][$z]['past_zone_traffic_fraction']
                            );
                            if (isset($aNotInLastOIPastPriorityResult[$a][$z]['impressions'])) {
                                $aFinalResult[$a][$z]['impressions'] = $aNotInLastOIPastPriorityResult[$a][$z]['impressions'];
                            }
                        }
                    }
                }
            }
        }
        return $aFinalResult;
    }

    /**
     * A private method for calculating the required and requested impressions,
     * priority factor, and past zone traffic fraction for an ad/zone pair, based on
     * data taken from the data_summary_ad_zone_assoc table. The values may be simply
     * those extracted from the database table, or they may be "average" values,
     * based on the length of time each of a number of different rows was active for
     * in the delivery engine.
     *
     * @access private
     *
     * @param array $aPastPriorityResult A reference to an array of arrays indexed
     *                                   by ad ID, and then zone ID, with each sub-array
     *                                   returned containing the details above.
     * @param array $aResult An array containing the results of a database query, with
     *                       the ad_id, zone_id, required_impressions, requested_impressions,
     *                       priority_factor, past_zone_traffic_fraction, created and expired
     *                       columns from the data_summary_ad_zone_assoc table, representing
     *                       these values for a complete (single) operation interval.
     *                       May optionally contain the operation_interval,
     *                       operation_interval_id, interval_start and interval_end details.
     * @param Date $oDate The current Date object, taken from the ServiceLocator.
     * @param array $aPastDeliveryResult Optional array of arrays indexed by ad ID, and then
     *                                   zone ID, containing details on which ad/zone
     *                                   combinations already have had their average past
     *                                   priority information calculated (if any).
     */
    function _calculateAveragePastPriorityValues(&$aPastPriorityResult, $aResult, $oDate, $aPastDeliveryResult = null)
    {
        if (is_array($aResult) && (!empty($aResult))) {
            // Loop through the results, and ensure that an array exists for each
            // ad ID, zone ID pair, and also store the initial values of the data
            foreach ($aResult as $aRow) {
                if (!isset($aPastPriorityResult[$aRow['ad_id']])) {
                    $aPastPriorityResult[$aRow['ad_id']] = array();
                }
                if (!isset($aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']])) {
                    $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']] = array(
                        'ad_id'                         => $aRow['ad_id'],
                        'zone_id'                       => $aRow['zone_id'],
                        'required_impressions'          => $aRow['required_impressions'],
                        'requested_impressions'         => $aRow['requested_impressions'],
                        'priority_factor'               => $aRow['priority_factor'],
                        'past_zone_traffic_fraction'    => $aRow['past_zone_traffic_fraction']
                    );
                    if (isset($aRow['operation_interval'])) {
                        $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['operation_interval']    = $aRow['operation_interval'];
                        $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['operation_interval_id'] = $aRow['operation_interval_id'];
                        $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['interval_start']        = $aRow['interval_start'];
                        $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['interval_end']          = $aRow['interval_end'];
                    }
                }
            }
            // As more than one row of past priority information may have been found
            // in the data, re-loop over the array of results, to see if the values
            // changed during operation interval the data represents
            foreach ($aResult as $aRow) {
                // Compare set values from above, and if there is a difference,
                // set values to zero, and mark as requiring calculation of the
                // averate impressions requested and priority factor
                if ((($aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['required_impressions'] != $aRow['required_impressions']) ||
                     ($aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['requested_impressions'] != $aRow['requested_impressions']) ||
                     ($aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['priority_factor'] != $aRow['priority_factor'])) &&
                     (!$aPastDeliveryResult[$aRow['ad_id']][$aRow['zone_id']]['pastPriorityFound'])) {
                    $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['required_impressions'] = 0;
                    $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['requested_impressions'] = 0;
                    $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['priority_factor'] = 0;
                    $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['average'] = true;
                    unset($aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['pastPriorityFound']);
                } else {
                    $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['pastPriorityFound'] = true;
                }
            }
            // Loop again, summing in the impressions, priorities (scaled by the length of time
            // they were active for) to the values, for those ad/zone combinations that require it
            foreach ($aResult as $aRow) {
                // Does this value need to be used to calculate the average?
                if ($aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['average'] &&
                    (!$aPastDeliveryResult[$aRow['ad_id']][$aRow['zone_id']]['pastPriorityFound'])) {
                    // Add the variable values to the array, multiplied by the number of seconds the
                    // values were active for over the "hour" (not exact, in the event that the
                    // maintenance script is not run spot on the hour, but close enough)
                    $oCreatedDate = new Date($aRow['created']);
                    if (is_null($aRow['expired'])) {
                        $oExpiredDate = new Date();
                        $oExpiredDate->copy($oDate);
                    } else {
                        $oExpiredDate = new Date($aRow['expired']);
                    }
                    $oSpan = new Date_Span();
                    $oSpan->setFromDateDiff($oCreatedDate, $oExpiredDate);
                    $seconds = $oSpan->toSeconds();
                    $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['required_impressions'] +=
                        $aRow['required_impressions'] * $seconds;
                    $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['requested_impressions'] +=
                        $aRow['requested_impressions'] * $seconds;
                    $aPastPriorityResult[$aRow['ad_id']][$aRow['zone_id']]['priority_factor'] +=
                        $aRow['priority_factor'] * $seconds;
                }
            }
            // Calculate the average impressions requested and priority factor used, for those
            // ad/zone combinations that require it
            if (!empty($aPastPriorityResult)) {
                foreach ($aPastPriorityResult as $a => $aAd) {
                    if (is_array($aAd) && (count($aAd) > 0)) {
                        foreach ($aAd as $z => $aZone) {
                            if ($aPastPriorityResult[$a][$z]['average'] && (!$aPastPriorityResult[$a][$z]['pastPriorityFound'])) {
                                $aPastPriorityResult[$a][$z]['required_impressions'] /= SECONDS_PER_HOUR;
                                $aPastPriorityResult[$a][$z]['requested_impressions'] /= SECONDS_PER_HOUR;
                                $aPastPriorityResult[$a][$z]['priority_factor'] /= SECONDS_PER_HOUR;
                                $aPastPriorityResult[$a][$z]['pastPriorityFound'] = true;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * A method to update the priority values stored in the database.
     *
     * Requires that a current day/time (as a Date object) be registered
     * with the ServiceLocator (as "now").
     *
     * @access public
     * @param array $aData An array of zones, indexed by zone ID, each containing an array
     *                     "ads", which in turn is an array of hashes, each containing the
     *                     following indexes/data:
     *                      - "ad_id"                   The ad ID.
     *                      - "zone_id"                 The zone ID.
     *                      - "priority"                The ad/zone priority value.
     *                      - "impressions_required"    The number of impressions required for the
     *                                                  placement the ad/zone link is in to delivery
     *                                                  to meet its targets.
     *                      - "impressions_requested"   The number of impressions the priority
     *                                                  should result in.
     *                      - "priority_factor"         The priority adjustment factor used to
     *                                                  compensate for filters.
     *                      - "priority_factor_limited" If the priority factor was limited, or not.
     *                      - "past_zone_traffic_fraction"  The fraction of the zone's impressions
     *                                                      given to the ad in the previous operation
     *                                                      interval.
     * @return boolean True on success, false on failure.
     *
     * @TODO Update the "created_by", "expired_by" fields once Max has a role-based
     *       permissions system in place.
     * @TODO Write the code that inserts non-static priorities.
     */
    function updatePriorities(&$aData)
    {
        MAX::debug('Saving calculated priorities.', PEAR_LOG_DEBUG);
        $conf = $GLOBALS['_MAX']['CONF'];
        $oServiceLocator = &ServiceLocator::instance();
        $oDate = &$oServiceLocator->get('now');
        if (!$oDate) {
            return false;
        }
        $currentOperationIntervalID = MAX_OperationInterval::convertDateToOperationIntervalID($oDate);
        $aDates = MAX_OperationInterval::convertDateToOperationIntervalStartAndEndDates($oDate);
        // Start a transaction
        $this->dbh->startTransaction();
        // Delete all category-based (ie. link_type = MAX_AD_ZONE_LINK_CATEGORY) priorities
        // from ad_zone_assoc
        $query = "
            DELETE FROM
                {$conf['table']['prefix']}{$conf['table']['ad_zone_assoc']}
            WHERE
                link_type = " . MAX_AD_ZONE_LINK_CATEGORY;
        $result = $this->dbh->query($query);
        if (PEAR::isError($result)) {
            $this->dbh->rollback();
            return false;
        }
        // Set all remaining normal (ie. link_type = MAX_AD_ZONE_LINK_NORMAL) priorities to zero
        $query = "
            UPDATE
                {$conf['table']['prefix']}{$conf['table']['ad_zone_assoc']}
            SET
                priority = 0
            WHERE
                link_type = " . MAX_AD_ZONE_LINK_NORMAL;
        $result = $this->dbh->query($query);
        if (PEAR::isError($result)) {
            $this->dbh->rollback();
            return false;
        }
        // Update the static priority values
        if (is_array($aData) && (count($aData) > 0)) {
            foreach ($aData as $aZoneData) {
                if (is_array($aZoneData['ads']) && (count($aZoneData['ads']) > 0)) {
                    foreach ($aZoneData['ads'] as $aAdZonePriority) {
                        $query = "
                            UPDATE
                                {$conf['table']['prefix']}{$conf['table']['ad_zone_assoc']}
                            SET
                                priority = {$aAdZonePriority['priority']}
                            WHERE
                                ad_id = {$aAdZonePriority['ad_id']}
                                AND zone_id = {$aAdZonePriority['zone_id']}
                                AND link_type = " . MAX_AD_ZONE_LINK_NORMAL;
                        $result = $this->dbh->query($query);
                        if (PEAR::isError($result)) {
                            $this->dbh->rollback();
                            return false;
                        }
                    }
                }
            }
        }
        // Insert the non-static priority values

        // Expire the old priority values in data_summary_ad_zone_assoc
        $query = "
            UPDATE
                {$conf['table']['prefix']}{$conf['table']['data_summary_ad_zone_assoc']}
            SET
                expired = '" . $oDate->format('%Y-%m-%d %H:%M:%S') . "',
                expired_by = 0
            WHERE
                expired IS NULL";
        $result = $this->dbh->query($query);
        if (PEAR::isError($result)) {
            $this->dbh->rollback();
            return false;
        }
        // Add the new priority values to data_summary_ad_zone_assoc
        // This query can get quite large, but MySQL (for example) only supports
        // a 1MB packet; as a result, this insert may need to be split up into
        // multiple statements; an insert could be up to 1k per ad/zone
        // association, therefore limit inserts to 500 ad/zone associations.
        if (is_array($aData) && !empty($aData)) {
            $counter = 0;
            $insertCounter = 0;
            $aValues = array();
            foreach ($aData as $aZoneData) {
                if (is_array($aZoneData['ads']) && !empty($aZoneData['ads'])) {
                    foreach ($aZoneData['ads'] as $aAdZonePriority) {
                        $aValues[$insertCounter][] = "
                            (
                                {$conf['maintenance']['operationInterval']},
                                $currentOperationIntervalID,
                                '" . $aDates['start']->format('%Y-%m-%d %H:%M:%S') . "',
                                '" . $aDates['end']->format('%Y-%m-%d %H:%M:%S') . "',
                                {$aAdZonePriority['ad_id']},
                                {$aAdZonePriority['zone_id']},
                                {$aAdZonePriority['required_impressions']},
                                {$aAdZonePriority['requested_impressions']},
                                {$aAdZonePriority['priority']},
                                " . (is_null($aAdZonePriority['priority_factor']) ? 'NULL' : $aAdZonePriority['priority_factor']) . ",
                                " . ($aAdZonePriority['priority_factor_limited'] ? 1 : 0) . ",
                                " . (is_null($aAdZonePriority['past_zone_traffic_fraction']) ? 'NULL' : $aAdZonePriority['past_zone_traffic_fraction']) . ",
                                '" . $oDate->format('%Y-%m-%d %H:%M:%S') . "',
                                0
                            )";
                        $counter++;
                        if (($counter % 500) == 0) {
                            $insertCounter++;
                        }
                    }
                }
            }
            if (is_array($aValues) && !empty($aValues)) {
                foreach ($aValues as $aInsertValues) {
                    if (is_array($aInsertValues) && !empty($aInsertValues)) {
                        $query = "
                            INSERT INTO
                                {$conf['table']['prefix']}{$conf['table']['data_summary_ad_zone_assoc']}
                                (
                                    operation_interval,
                                    operation_interval_id,
                                    interval_start,
                                    interval_end,
                                    ad_id,
                                    zone_id,
                                    required_impressions,
                                    requested_impressions,
                                    priority,
                                    priority_factor,
                                    priority_factor_limited,
                                    past_zone_traffic_fraction,
                                    created,
                                    created_by
                                )
                             VALUES" . implode(', ', $aInsertValues);
                        $result = $this->dbh->query($query);
                        if (PEAR::isError($result)) {
                            $this->dbh->rollback();
                            return false;
                        }
                    }
                }
            }
        }
        // Commit the transaction
        $this->dbh->commit();
        return true;
    }

    /**
     * A method to get the average number of impressions recorded -- calculated
     * over the required number of past weeks -- for each active zone.
     *
     * @param array $aZoneIds       array of zoneIds to find averages of
     * @param object $oStartDate    Date object representing the beginning of the start
     *                              operation interval range to be evaluated
     * @param object $oEndDate      Date object representing the beginning of the end
     *                              operation interval in range to be evaluated
     * @param integer $weeks        The number of past weeks to average over.
     * @return array
     *
     *  <pre>
     *  Array =>
     *      [zone_id] => Array
     *          (
     *              [operation_interval_id] => Array
     *                  (
     *                      ['average_impressions'] => Average Impressions
     *                  )
     *              [operation_interval_id] => Array
     *                  (
     *                      ['average_impressions'] => Average Impressions
     *                  )
     *          )
     *      [zone_id] => Array
     *          (
     *              [operation_interval_id] => Array
     *                  (
     *                      ['average_impressions'] => Average Impressions
     *                  )
     *              [operation_interval_id] => Array
     *                  (
     *                      ['average_impressions'] => Average Impressions
     *                  )
     *          )
     *  </pre>
     */
     function getZonesImpressionAverageByRange($aZoneIds, $oStartDate, $oEndDate, $weeks)
     {
        if ((!is_array($aZoneIds)) || (!count($aZoneIds))) {
            return;
        }
        $conf = $GLOBALS['_MAX']['CONF'];
        // Clone date parameters
        $oStartDateCopy = new Date();
        $oStartDateCopy->copy($oStartDate);
        $oEndDateCopy = new Date();
        $oEndDateCopy->copy($oEndDate);
        // Construct the date constraints for the query
        $dateConstraints = "(";
        $aDateConstraints = array();
        for($i = 0; $i < $weeks; $i++) {
            $oStartDateCopy->subtractSeconds(SECONDS_PER_WEEK);
            $oEndDateCopy->subtractSeconds(SECONDS_PER_WEEK);
            $aDateConstraints[] = "
                (
                    interval_start >= '" . $oStartDateCopy->format('%Y-%m-%d %H:%M:%S') . "'
                    AND interval_start <= '" . $oEndDateCopy->format('%Y-%m-%d %H:%M:%S') . "'
                )";
        }
        $dateConstraints .= implode(' OR ', $aDateConstraints);
        $dateConstraints .= ")";
        // Form the database query
        $query = "
            SELECT
                zone_id AS zone_id,
                operation_interval_id AS operation_interval_id,
                ROUND(SUM(actual_impressions)/COUNT(actual_impressions)) AS average_impressions
            FROM
                {$conf['table']['prefix']}{$conf['table']['data_summary_zone_impression_history']}
            WHERE
                zone_id IN (" . implode(', ', $aZoneIds) . ")
                AND operation_interval = {$conf['maintenance']['operationInterval']}
                AND " . $dateConstraints . "
            GROUP BY
                zone_id, operation_interval_id
            HAVING
                COUNT(actual_impressions) = $weeks";
        // Execute the query
        $res = $this->dbh->query($query);
        // Store the average impressions data
        if (!(PEAR::isError($res))) {
            $return = array();
            while ($res->fetchInto($row)) {
                $return[$row['zone_id']][$row['operation_interval_id']] = $row;
            }
            return $return;
        } else {
            return $res;
        }
    }

    /**
     * A method to get the number of forecast and actual impressions recorded
     * for each active zone, over a given range.
     *
     * @param array $aZoneIds    array of zoneIds to find averages of
     * @param object $oStartDate Date representing start of operation interval for range
     * @param object $oEndDate   Date representing start of the end operation interval for range
     * @return array
     *  <pre>
     *   Array
     *   (
     *       [zone_id] => Array
     *           (
     *               [operation_interval_id] => Array
     *                   (
     *                       [zone_id] => 1
     *                       [operation_interval_id] => 1
     *                       [forecast_impressions] => 200
     *                       [actual_impressions] => 400
     *                   )
     *
     *               [operation_interval_id] => Array
     *                   (
     *                       [zone_id] => 1
     *                       [operation_interval_id] => 2
     *                       [forecast_impressions] => 200
     *                       [actual_impressions] => 400
     *                   )
     *           )
     *
     *   )
     *      .
     *      .
     *      .
     *  </pre>
     */
    function getZonesImpressionHistoryByRange($aZoneIds, $startDate, $endDate)
    {
        if ((!is_array($aZoneIds)) || (!count($aZoneIds))) {
            return;
        }
        $conf = $GLOBALS['_MAX']['CONF'];
        // Construct the SQL to obtain the impressions
        $query = "
            SELECT
                zone_id AS zone_id,
                operation_interval_id AS operation_interval_id,
                forecast_impressions AS forecast_impressions,
                actual_impressions AS actual_impressions
            FROM
                {$conf['table']['prefix']}{$conf['table']['data_summary_zone_impression_history']}
            WHERE
                zone_id IN (" . implode(', ', $aZoneIds) . ")
                AND operation_interval = {$conf['maintenance']['operationInterval']}
                AND interval_start >= '" . $startDate->format('%Y-%m-%d %H:%M:%S') . "'
                AND interval_start <= '" . $endDate->format('%Y-%m-%d %H:%M:%S') . "'";
        $res = $this->dbh->query($query);
        // Store the impression data
        if (!(PEAR::isError($res))) {
            $return = array();
            while ($res->fetchInto($row)) {
                $return[$row['zone_id']][$row['operation_interval_id']] = $row;
            }
            return $return;
        } else {
            return $res;
        }
    }

    /**
     * A method to save zone impression forecasts to the stats_zone_impression_history table.
     *
     * @param array $aForecasts A reference to an array that contains the zone impression
     *                         forecasts, indexed by zone ID, and then operation interval
     *                         ID. Each entry is expected to be an array with the the
     *                         following indicies: "forecast" => The forecast number of
     *                         impressions for the zone/operation interval;
     *                         "interval_start" => The start Date for the operation interval;
     *                         "interval_end" => The end Date for the operation interval; and
     *                         "operation_interval" => The operation interval in use when
     *                         the forecast was made.
     * @param boolean $safe A flag to indicate if it is safe to simply insert the values
     *                      (true), or if each forecast needs to be checked to see if it
     *                      may already exist (false).
     */
    function saveZoneImpressionForecasts($aForecasts, $safe = true)
    {
        // Check the parameter
        if (!is_array($aForecasts) || (is_array($aForecasts) && (count($aForecasts) == 0))) {
            return;
        }
        $conf = $GLOBALS['_MAX']['CONF'];
        if ($safe) {
            // Construct the SQL statements to insert the values
            $query = "
                INSERT INTO
                    {$conf['table']['prefix']}{$conf['table']['data_summary_zone_impression_history']}
                    (
                        zone_id,
                        operation_interval,
                        operation_interval_id,
                        interval_start,
                        interval_end,
                        forecast_impressions
                    )
                VALUES";
            foreach ($aForecasts as $zoneId => $aOperationIntervals) {
                foreach ($aOperationIntervals as $id => $aValues) {
                    $aQuery[] = "($zoneId, {$conf['maintenance']['operationInterval']}, $id, '" .
                        $aValues['interval_start'] . "', '" .
                        $aValues['interval_end'] . "', {$aValues['forecast_impressions']})";
                }
            }
            $query .= ' ' . implode(', ', $aQuery);
            // Insert the new historical zone impressions forecasts
            $result = $this->dbh->query($query);
        } else {
            // For each forecast in the array
            foreach ($aForecasts as $zoneId => $aOperationIntervals) {
                foreach ($aOperationIntervals as $id => $aValues) {
                    // Is there already a forecast for the zone?
                    $query = "
                        SELECT
                            forecast_impressions AS forecast_impressions
                        FROM
                            {$conf['table']['prefix']}{$conf['table']['data_summary_zone_impression_history']}
                        WHERE
                            zone_id = $zoneId
                            AND operation_interval = {$conf['maintenance']['operationInterval']}
                            AND operation_interval_id = $id
                            AND interval_start = '{$aValues['interval_start']}'
                            AND interval_end = '{$aValues['interval_end']}'";
                    $result = $this->dbh->query($query);
                    if ($result->numRows() > 0) {
                        // Update the existing forecast
                        $query = "
                            UPDATE
                                {$conf['table']['prefix']}{$conf['table']['data_summary_zone_impression_history']}
                            SET
                                forecast_impressions = {$aValues['forecast_impressions']}
                            WHERE
                                zone_id = $zoneId
                                AND operation_interval = {$conf['maintenance']['operationInterval']}
                                AND operation_interval_id = $id
                                AND interval_start = '{$aValues['interval_start']}'
                                AND interval_end = '{$aValues['interval_end']}'";
                        $result = $this->dbh->query($query);
                    } else {
                        // Insert a new forecast
                        $query = "
                            INSERT INTO
                                {$conf['table']['prefix']}{$conf['table']['data_summary_zone_impression_history']}
                                (
                                    zone_id,
                                    operation_interval,
                                    operation_interval_id,
                                    interval_start,
                                    interval_end,
                                    forecast_impressions
                                )
                            VALUES
                                (
                                    $zoneId,
                                    {$conf['maintenance']['operationInterval']},
                                    $id,
                                    '{$aValues['interval_start']}',
                                    '{$aValues['interval_end']}',
                                    {$aValues['forecast_impressions']}
                                )";
                        $result = $this->dbh->query($query);
                    }
                }
            }
        }
    }

    /**
     * A method to get all active zones in the system.
     *
     * An active zone is a zone that is linked to at least one advertisement
     * (via the ad_zone_assoc table), where the advertisement has its "active"
     * field set to true.
     */
    function getActiveZones()
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        $query = array();
        $table             = $conf['table']['prefix'] . $conf['table']['zones'];
        $joinTable1        = $conf['table']['prefix'] . $conf['table']['ad_zone_assoc'];
        $joinTable2        = $conf['table']['prefix'] . $conf['table']['banners'];
        $query['table']    = $table;
        $query['fields']   = array(
                                "$table.zoneid",
                                "$table.zonename",
                                "$table.zonetype"
                             );
        $query['joins']    = array(
                                array($joinTable1, "$table.zoneid = $joinTable1.zone_id"),
                                array($joinTable2, "$joinTable1.ad_id = $joinTable2.bannerid AND $joinTable2.active = 't'")
                             );
        $query['group']    = "zoneid";
        return $this->_get($query);
    }

    /**
     * A method to save the total impressions required for each advert
     * into the temporary tmp_ad_required_impression table.
     *
     * @param array $aData An array of array, each containing the
     *                     "ad_id", and its associated "required_impressions".
     */
    function saveRequiredAdImpressions($aData)
    {
        if (is_array($aData) && (count($aData) > 0)) {
            $values = array();
            foreach ($aData as $data) {
                $values[] = "
                    (
                        {$data['ad_id']},
                        {$data['required_impressions']}
                    )";
            }
            $query = "
                INSERT INTO
                    tmp_ad_required_impression
                    (
                        ad_id,
                        required_impressions
                    )
                VALUES" . implode(', ', $values);
            $this->dbh->query($query);
        }
    }

    /**
     * A method to get the required impressions for a list of advertisements
     * from the tmp_ad_required_impression table.
     *
     * @param array $aAdvertID An array of advertisement IDs.
     * @return array An array of required impressions, indexed by advertisement ID.
     */
    function getRequiredAdImpressions($aAdvertID)
    {
        if (empty($aAdvertID)) {
            return array();
        }
        $conf = $GLOBALS['_MAX']['CONF'];
        $query = "
            SELECT
                ad_id AS ad_id,
                required_impressions AS required_impressions
            FROM
                tmp_ad_required_impression
            WHERE
                ad_id IN (" . implode(', ', $aAdvertID) . ')';
        // Don't use a PEAR_Error handler
        PEAR::pushErrorHandling(null);
        // Execute the query
        $result = $this->dbh->query($query);
        // Resore the PEAR_Error handler
        PEAR::popErrorHandling();
        if (!PEAR::isError($result)) {
            $aResult = array();
            while ($row = $result->fetchRow()) {
                $aResult[$row['ad_id']] = $row['required_impressions'];
            }
            return $aResult;
        } elseif (PEAR::isError($result, DB_ERROR_NOSUCHTABLE)) {
            return array();
        }
        MAX::raiseError($result, MAX_ERROR_DBFAILURE, PEAR_ERROR_DIE);
    }

    /**
     * A method to get current zone impression forecast(s) for every zone in the
     * system. Where no impression forecast exists for a zone, the default zone
     * impression forecast value is used. Similarly, if the zone forecast is less
     * that the default zone forecast, the default zone impression forecast is
     * used.
     *
     * Requires that a current day/time (as a Date object) be registered
     * with the ServiceLocator (as "now").
     *
     * Ignores the special zone with zone ID of 0 (zero), as this is a
     * reserved zone for storing statistics about directly selected ads,
     * and as a result, shouldn't be used during prioritisation.
     *
     * @return mixed An array, indexed by zone ID, of the current impression
     *               forecasts, or false if the current datetime not registered
     *               with the ServiceLocator.
     */
    function getZoneImpressionForecasts()
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        $oServiceLocator = &ServiceLocator::instance();
        $oDate = &$oServiceLocator->get('now');
        if (!$oDate) {
            return false;
        }
        $aResult = array();
        // Get the zone impression forecasts for the current operation interval, where they exist
        $currentOpIntID = MAX_OperationInterval::convertDateToOperationIntervalID($oDate);
        $aCurrentDates = MAX_OperationInterval::convertDateToOperationIntervalStartAndEndDates($oDate);
        $query = array();
        $table             = $conf['table']['prefix'] . $conf['table']['data_summary_zone_impression_history'];
        $query['table']    = $table;
        $query['fields']   = array(
                                "$table.zone_id AS zone_id",
                                "$table.forecast_impressions AS forecast_impressions"
                             );
        $query['wheres']   = array(
                                 array("$table.operation_interval = {$conf['maintenance']['operationInterval']}", 'AND'),
                                 array("$table.operation_interval_id = $currentOpIntID",  'AND'),
                                 array("$table.interval_start = '" . $aCurrentDates['start']->format('%Y-%m-%d %H:%M:%S') . "'", 'AND'),
                                 array("$table.interval_end = '" . $aCurrentDates['end']->format('%Y-%m-%d %H:%M:%S') . "'", 'AND'),
                                 array("$table.zone_id != 0", 'AND')
                             );
        $result = $this->_get($query);
        foreach ($result as $row) {
            $aResult[$row['zone_id']] = $row['forecast_impressions'];
        }
        // Get all possible zones in the system
        $query = array();
        $table             = $conf['table']['prefix'] . $conf['table']['zones'];
        $query['table']    = $table;
        $query['fields']   = array(
                                "$table.zoneid AS zone_id"
                             );
        $query['wheres']   = array(
                                 array("$table.zoneid != 0", 'AND')
                             );
        $result = $this->_get($query);
        foreach ($result as $row) {
            if ((!isset($aResult[$row['zone_id']])) || ($aResult[$row['zone_id']] < $conf['priority']['defaultZoneForecastImpressions'])) {
                $aResult[$row['zone_id']] = $conf['priority']['defaultZoneForecastImpressions'];
            }
        }
        // Return the result
        return $aResult;
    }

    /**
     * A method to get the ad/zone associations, based on a list of advertisement IDs.
     * Only returns normal ad/zone associations (ie. link_type = MAX_AD_ZONE_LINK_NORMAL),
     * not special direct selection associations, or category associations.
     *
     * @param array $aAdvertID An array of advertisement IDs.
     * @return mixed An array of arrays, indexed by advertisement ID, with each
     *               sub-array containing the zones that the adversisement is linked to.
     *               For example:
     *               array(
     *                   7 => array(
     *                            0 => array('zone_id' => 9),
     *                            1 => array('zone_id' => 42),
     *                                  .
     *                                  .
     *                                  .
     *                        )
     *                   .
     *                   .
     *                   .
     *               )
     */
    function getAdZoneAssociationsByAds($aAdvertID)
    {
        if (!is_array($aAdvertID) || empty($aAdvertID)) {
            return array();
        }
        $conf = $GLOBALS['_MAX']['CONF'];
        $query = array();
        $table             = $conf['table']['prefix'] . $conf['table']['ad_zone_assoc'];
        $query['table']    = $table;
        $query['fields']   = array(
                                "$table.ad_id AS ad_id",
                                "$table.zone_id AS zone_id"
                             );
        $query['wheres']   = array(
                                array("$table.ad_id IN (" . implode(', ', $aAdvertID) . ')', 'AND'),
                                array("$table.link_type = " . MAX_AD_ZONE_LINK_NORMAL, 'AND')
                             );
        $result = $this->_get($query);
        $aResult = array();
        foreach ($result as $row) {
            $aResult[$row['ad_id']][] = array('zone_id' => $row['zone_id']);
        }
        return $aResult;
    }

    /**
     * A method to save ad/zone impression allocations to the tmp_ad_zone_impression
     * table.
     *
     * @param array An array of arrays, with each sub-array containing the "ad_id",
     *              "zone_id", "required_impressions" and "requested_impressions".
     */
    function saveAllocatedImpressions($aData)
    {
        if (is_array($aData) && (count($aData) > 0)) {
            $aValues = array();
            foreach ($aData as $aItem) {
                $aValues[] = "({$aItem['ad_id']}, {$aItem['zone_id']},
                               {$aItem['required_impressions']}, {$aItem['requested_impressions']})";
            }
            $query = "
                INSERT INTO
                    tmp_ad_zone_impression
                    (
                        ad_id,
                        zone_id,
                        required_impressions,
                        requested_impressions
                    )
                VALUES ";
            $query .= implode(', ', $aValues);
            $this->dbh->query($query);
        }
    }

   /**
    * A method to return the forcast impressions for a zone, indexed by operation interval,
    * from the current operation interval through the past week. If no forecast stored in
    * the database, uses the defualt value from the configuration file.
    *
    * @param integer $zoneId The Zone ID.
    * @return mixed An array on success, false on failure. The array is of the format:
    *                   array(
    *                       [operation_interval_id] => array(
    *                                                      ['zone_id']               => zone_id,
    *                                                      ['forecast_impressions']  => forecast_impressions,
    *                                                      ['operation_interval_id'] => operation_interval_id
    *                                                  )
    *                       [operation_interval_id] => array(
    *                                                      ['zone_id']               => zone_id,
    *                                                      ['forecast_impressions']  => forecast_impressions,
    *                                                      ['operation_interval_id'] => operation_interval_id
    *                                                  )
    *                                   .
    *                                   .
    *                                   .
    *                   )
    */
    function getPreviousWeekZoneForcastImpressions($zoneId)
    {
        if (empty($zoneId) || !is_numeric($zoneId)) {
            MAX::debug('Invalid zone ID argument', PEAR_LOG_ERR);
            return false;
        }
        $conf = $GLOBALS['_MAX']['CONF'];
        $oServiceLocator = &ServiceLocator::instance();
        $oDate = &$oServiceLocator->get('now');
        if (!$oDate) {
            return false;
        }
        // Get the start and end ranges of the current week
        $aDates = MAX_OperationInterval::convertDateToOperationIntervalStartAndEndDates($oDate);
        $oDateWeekStart = new Date();
        $oDateWeekStart->copy($aDates['end']);
        $oDateWeekStart->subtractSeconds(SECONDS_PER_WEEK - 1);
        $oDateWeekEnd = new Date();
        $oDateWeekEnd->copy($aDates['end']);
        // Select the zone forecasts from the database
        $query = "
            SELECT
                zone_id AS zone_id,
                forecast_impressions AS forecast_impressions,
                operation_interval_id AS operation_interval_id,
                interval_start AS interval_start,
                interval_end AS interval_end
            FROM
                {$conf['table']['prefix']}{$conf['table']['data_summary_zone_impression_history']}
            WHERE
                zone_id = $zoneId
                AND operation_interval = {$conf['maintenance']['operationInterval']}
                AND interval_start >= '" . $oDateWeekStart->format('%Y-%m-%d %H:%M:%S') . "'
                AND interval_end <= '" . $oDateWeekEnd->format('%Y-%m-%d %H:%M:%S') . "'
                AND zone_id != 0
            ORDER BY
                interval_start";
        $result = $this->dbh->query($query);
        if (!(PEAR::isError($result))) {
            // Sort the results into an array indexed by the operation interval ID
            $aFinalResult = array();
            while ($result->fetchInto($aRow)) {
                $aFinalResult[$aRow['operation_interval_id']] =
                    array(
                        'zone_id'               => $aRow['zone_id'],
                        'forecast_impressions'  => $aRow['forecast_impressions'],
                        'operation_interval_id' => $aRow['operation_interval_id']
                    );
            }
        }
        // Check each operation interval ID has a forecast impression value,
        // and if not, set to the system default.
        for ($i = 0; $i < MAX_OperationInterval::operationIntervalsPerWeek(); $i++) {
            if (!isset($aFinalResult[$i])) {
                $aFinalResult[$i] = array(
                    'zone_id'               => $zoneId,
                    'forecast_impressions'  => $conf['priority']['defaultZoneForecastImpressions'],
                    'operation_interval_id' => $i,
                );
            }
        }
        return $aFinalResult;
    }

    /**
     * Obtains a database-level lock for the Maintenance Priority Engine process.
     *
     * @return boolean True if lock was obtained, false otherwise.
     */
    function obtainPriorityLock()
    {
        return parent::obtainLock('mpe');
    }

    /**
     * Releases the database-level lock for the Maintenance Priority Engine process.
     *
     * @return mixed True if lock was released, a PEAR Error otherwise.
     */
    function releasePriorityLock()
    {
        return parent::releaseLock('mpe');
    }

}

?>
