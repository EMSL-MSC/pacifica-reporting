<?php
/**
 * Pacifica
 *
 * Pacifica is an open-source data management framework designed
 * for the curation and storage of raw and processed scientific
 * data. It is based on the [CodeIgniter web framework](http://codeigniter.com).
 *
 *  The Pacifica-Reporting module provides an interface for
 *  concerned and interested parties to view the current
 *  contribution status of any and all instruments in the
 *  system. The reporting interface can be customized and
 *  filtered streamline the report to fit any level of user,
 *  from managers through instrument operators.
 *
 * PHP version 5.5
 *
 * @package Pacifica-reporting
 *
 * @author  Ken Auberry <kenneth.auberry@pnnl.gov>
 * @license BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @link http://github.com/EMSL-MSC/Pacifica-reporting
 */

/**
 *  Reporting Model
 *
 *  The **Search_model** class contains functionality
 *  for retrieving metadata entries from the policy server.
 *
 * @category CI_Model
 * @package  Pacifica-reporting
 * @author   Ken Auberry <kenneth.auberry@pnnl.gov>
 *
 * @license BSD https://opensource.org/licenses/BSD-3-Clause
 * @link    http://github.com/EMSL-MSC/Pacifica-reporting
 *
 * @access public
 */
class Compliance_model extends CI_Model
{
    /**
     *  Class constructor
     *
     * @method __construct
     *
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->library('PHPRequests');
        $this->metadata_url_base = $this->config->item('metadata_server_base_url');
        $this->policy_url_base = $this->config->item('policy_server_base_url');
        $this->content_type = "application/json";
        $this->eusDB = $this->load->database('eus', TRUE);
        $this->instrument_cache = array();
        $this->instrument_group_cache = array();
        $this->proposal_cache = array();
    }

    /**
     * Get information about specific transactions from metadata_server_base_url
     * @param  string $object_type proposal or instrument
     * @param  array $id_list list of object id's to search framework
     * @return array object containing transaction info
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function retrieve_uploads_for_object_list($object_type, $id_list, $start_time_obj = FALSE, $end_time_obj = FALSE){
        $allowed_object_types = array('instrument', 'proposal');
        if(!in_array($object_type, $allowed_object_types)){
            return false;
        }

        $json_blob = array(
            'id_list' => $id_list,
            'start_time' => $start_time_obj->format('Y-m-d'),
            'end_time' => $end_time_obj->format('Y-m-d')
        );


        $uploads_url = "{$this->metadata_url_base}/transactioninfo/search/";
        $uploads_url .= $object_type;
        $query = Requests::post(
            $uploads_url,
            array('Content-Type' => 'application/json'),
            json_encode($json_blob),
            array('timeout' => 120)
        );
        if($query->success){
            return json_decode($query->body, TRUE);
        }
        return array();
    }

    /**
     * Get information regarding active proposals from the EUS database
     * @param  string $start_date the initial date in the period
     * @param  string $end_date the final date in the period
     * @return array list of activity, by proposal id and instrument_id
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function retrieve_active_proposal_list_from_eus($start_date_obj, $end_date_obj)
    {
        $column_array = array(
            'COUNT(BOOKING_ID) as booking_count',
            'RESOURCE_ID as instrument_id',
            'PROPOSAL_ID as proposal_id',
            'MIN(DATE_START) as date_start',
            'MAX(DATE_FINISH) as date_finish'
        );

        // $column_array = array(
        //     'BOOKING_ID as booking_id',
        //     'RESOURCE_ID as instrument_id',
        //     'IFNULL(PROPOSAL_ID, 0) as proposal_id',
        //     'LOWER(USAGE_CD) as usage_code',
        //     'DATE_START as date_start',
        //     'DATE_FINISH as date_finish',
        //     'IF(LOWER(EXCLUDE_WEEKENDS) = \'y\', TRUE, FALSE) as exclude_weekends'
        // );

        $query = $this->eusDB->select($column_array)->from("ERS_BOOKING")
            ->group_start()
                ->group_start()
                    ->where('DATE_START >=', $start_date_obj->format('Y-m-d'))
                    ->where('DATE_START <=', $end_date_obj->format('Y-m-d'))
                ->group_end()
                ->or_group_start()
                    ->where('DATE_FINISH >=', $start_date_obj->format('Y-m-d'))
                    ->where('DATE_FINISH <=', $end_date_obj->format('Y-m-d'))
                ->group_end()
            ->group_end()
            ->where('NOT ISNULL(PROPOSAL_ID)')
            ->group_by(array('PROPOSAL_ID', 'RESOURCE_ID'))
            ->order_by('instrument_id, date_start')
        ->get();

        $usage = array(
            'by_instrument' => array(),
            'by_proposal' => array()//,
            // 'group_instrument_lookup' => array()
        );
        $instrument_group_lookup = array();
        // $group_instrument_lookup = array();

        foreach($query->result() as $row){
            $inst_id = intval($row->instrument_id);
            if(!array_key_exists($inst_id, $instrument_group_lookup)){
                $group_id = $this->get_group_id($inst_id);
                $instrument_group_lookup[$inst_id] = $group_id;
            }
            $group_id = $instrument_group_lookup[$inst_id];

            $record_start_date = new DateTime($row->date_start);
            $record_end_date = new DateTime($row->date_finish);

            $entry = array(
                'booking_count' => $row->booking_count,
                'instrument_id' => $inst_id,
                'instrument_group_id' => $group_id,
                'proposal_id' => $row->proposal_id,
                'date_start' => $record_start_date,
                'date_finish' => $record_end_date,
                'transactions_list' => array(),
                'file_count' => 0
            );
            $inst_group_comp[] = $group_id;
            // $usage['by_instrument'][$inst_id]['bookings'][$row->booking_id] = $entry;
            $usage['by_proposal'][$row->proposal_id][$group_id][$inst_id] = $entry;
        }

        $ungrouped = $usage['by_proposal'];
        foreach($ungrouped as $proposal_id => $group_entries){
            foreach($group_entries as $group_id => $inst_entries){
                $new_entry = array();
                foreach($inst_entries as $inst_id => $entry){
                    if(empty($new_entry)){
                        $new_entry = $entry;
                        $new_entry['instruments_scheduled'] = array($new_entry['instrument_id']);
                        unset($new_entry['instrument_id']);
                    }else{
                        $new_entry['booking_count'] += $entry['booking_count'];
                        $new_entry['instruments_scheduled'][] = $entry['instrument_id'];
                        $new_entry['date_start'] = $entry['date_start'] < $new_entry['date_start']
                            ? $entry['date_start'] : $new_entry['date_start'];
                        $new_entry['date_finish'] = $entry['date_finish'] > $new_entry['date_finish']
                            ? $entry['date_finish'] : $new_entry['date_finish'];
                    };
                }
                $usage['by_proposal'][$proposal_id][$group_id] = $new_entry;
            }
        }

        // $usage['group_instrument_lookup'] = $group_instrument_lookup;
        $usage['instrument_group_compilation'] = array_unique($inst_group_comp);
        return $usage;
    }

    /**
     * Get the instrument grouping list for all instruments
     * @param  integer $instrument_id the instrument id to search
     * @return integer The group id of the instrument in question
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function get_group_id_cache(){
        $group_retrieval_url = "{$this->metadata_url_base}/instrument_group?";
        $url_args_array = array(
            'recursion_depth' => 0
        );
        $group_id_list = array();
        $group_retrieval_url .= http_build_query($url_args_array, '', '&');
        $query = Requests::get($group_retrieval_url, array('Accept' => 'application/json'));
        if($query->status_code == 200 && $query->body != '[]'){
            $results = json_decode($query->body, TRUE);
            foreach($results as $inst_entry){
                $group_id_list[$inst_entry['instrument_id']] = $inst_entry['group_id'];
            }
        }
        return $group_id_list;
    }


    /**
     * Get the instrument grouping id for a given instrument
     * @param  integer $instrument_id the instrument id to search
     * @return integer The group id of the instrument in question
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function get_group_id($instrument_id){
        if(array_key_exists($instrument_id, $this->instrument_group_cache)){
            return $this->instrument_group_cache[$instrument_id];
        }
        $group_retrieval_url = "{$this->metadata_url_base}/instrument_group?";
        $url_args_array = array(
            'instrument_id' => $instrument_id,
            'recursion_depth' => 0
        );
        $group_id = 0;
        $group_retrieval_url .= http_build_query($url_args_array, '', '&');
        $query = Requests::get($group_retrieval_url, array('Accept' => 'application/json'));
        if($query->status_code == 200 && $query->body != '[]'){
            $results = json_decode($query->body, TRUE);
            $inst_entry = array_shift($results);
            $group_id = $inst_entry['group_id'];
            $this->instrument_group_cache[$instrument_id] = $group_id;
        }
        return $group_id;
    }

    /**
     * Get the full list of instrument group ids and name
     * @return array list of instrument groups with id's
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function get_group_name_lookup(){
        $group_list = array();
        $group_retrieval_url = "{$this->metadata_url_base}/groups";
        $query = Requests::get($group_retrieval_url, array('Accept' => 'application/json'));
        if($query->status_code == 200 && $query->body != '[]'){
            $results = json_decode($query->body, TRUE);
            foreach($results as $group_entry){
                $group_list[$group_entry['_id']] = $group_entry['name'];
            }
        }
        $group_list[0] = "Unknown Instrument Group Type";
        return $group_list;
    }

    /**
     * Get the proposal name from the id
     * @param  integer $proposal_id the proposal_id to lookup
     * @return string the name of the proposal
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function get_proposal_name($proposal_id){
        if(array_key_exists($proposal_id, $this->proposal_cache)){
            return $this->proposal_cache[$proposal_id];
        }
        $proposal_url = "{$this->metadata_url_base}/proposals?";
        $url_args_array = array(
            '_id' => $proposal_id,
            'recursion_depth' => 0
        );
        $proposal_name = "Unknown Proposal {$proposal_id}";
        $proposal_url .= http_build_query($url_args_array, '', '&');
        $query = Requests::get($proposal_url, array('Accept' => 'application/json'));
        if($query->status_code == 200 && $query->body != '[]'){
            $results = json_decode($query->body, TRUE);
            $proposal_entry = array_shift($results);
            $proposal_name = $proposal_entry['title'];
            $this->proposal_cache[$proposal_id] = $proposal_name;
        }
        return $proposal_name;
    }

    /**
     * Get the proposal name from the id
     * @param  integer $instrument_id the instrument_id to lookup
     * @return string the name of the instrument
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function get_instrument_name($instrument_id){
        if(array_key_exists($instrument_id, $this->instrument_cache)){
            return $this->instrument_cache[$instrument_id];
        }
        $instrument_url = "{$this->metadata_url_base}/instruments?";
        $url_args_array = array(
            '_id' => $instrument_id,
            'recursion_depth' => 0
        );
        $instrument_name = "Unknown Instrument {$instrument_id}";
        $instrument_url .= http_build_query($url_args_array, '', '&');
        $query = Requests::get($instrument_url, array('Accept' => 'application/json'));
        if($query->status_code == 200 && $query->body != '[]'){
            $results = json_decode($query->body, TRUE);
            $instrument_entry = array_shift($results);
            $instrument_name = $instrument_entry['name'];
            $this->instrument_cache[$instrument_id] = $instrument_name;
        }
        return $instrument_name;
    }

    /**
     * Get a full set of instrument id's for a given instrument grouping
     * @param  integer $group_id The group id to search
     * @return array a list of the instrument id's for that group
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function get_instruments_for_group($group_id){
        $instrument_list = array();
        $instruments_retrieval_url = "{$this->metadata_url_base}/instrument_group?";
        $url_args_array = array(
            'group_id' => $group_id,
            'recursion_depth' => 0
        );
        $instruments_retrieval_url .= http_build_query($url_args_array, '', '&');
        $inst_query = Requests::get($instruments_retrieval_url, array('Accept' => 'application/json'));
        if($inst_query->status_code == 200){
            $inst_results = json_decode($inst_query->body, TRUE);
            foreach($inst_results as $entry){
                $instrument_list[] = $entry['instrument_id'];
            }
        }
        return $instrument_list;
    }

    public function cross_reference_bookings_and_data(
        $object_type, $eus_object_type_records,
        $start_time, $end_time
    ){
        $object_list = $eus_object_type_records["by_{$object_type}"];
        $inst_group_list = $eus_object_type_records["instrument_group_compilation"];
        $group_name_lookup = $this->get_group_name_lookup();
        $this->instrument_group_cache = $this->get_group_id_cache();
        $eus_objects = $object_list;

        $booking_stats_cache = array();
        $start_time->modify('-1 week');
        $end_time->modify('+3 weeks');

        $url_args_array = array(
            'start_time' => $start_time->format('Y-m-d'),
            'end_time' => $end_time->format('Y-m-d')
        );

        $url = "{$this->metadata_url_base}/transactioninfo/multisearch?";
        $url .= http_build_query($url_args_array, '', '&');
        $transactions_list_query = Requests::get($url, array('Accept' => 'application/json'));
        if($transactions_list_query->status_code == 200){
            $transactions_list = json_decode($transactions_list_query->body, TRUE);
            foreach($transactions_list as $transaction_id => $trans_info){
                $my_group_id = $this->get_group_id($trans_info['instrument_id']);
                $proposal_id = strval($trans_info['proposal_id']);
                $stats_template = array(
                    'booking_count' => 0,
                    'data_file_count' => 0,
                    'instruments_scheduled' => array(),
                    'transaction_list' => array()
                );
                if(!array_key_exists($proposal_id, $booking_stats_cache)){
                    $booking_stats_cache[$proposal_id] = array();
                }
                if(!array_key_exists($my_group_id, $booking_stats_cache)){
                    $booking_stats_cache[$proposal_id][$my_group_id] = $stats_template;
                }
                // if(!array_key_exists($my_group_id, $booking_stats_cache['by_group_id'])){
                //     $booking_stats_cache['by_group_id'][$my_group_id] = array();
                // }
                // if(!array_key_exists($proposal_id, $booking_stats_cache['by_group_id'])){
                //     $booking_stats_cache['by_group_id'][$my_group_id][$proposal_id] = $stats_template;
                // }
                $booking_stats_cache[$proposal_id][$my_group_id]['data_file_count']
                    += $trans_info['file_count'];
                // $booking_stats_cache['by_group_id'][$my_group_id][$proposal_id]['data_file_count']
                //     += $trans_info['file_count'];
                $booking_stats_cache[$proposal_id][$my_group_id]['transaction_list'][$trans_info['upload_date']][]
                    = array(
                        'transaction_id' => $transaction_id,
                        'file_count' => intval($trans_info['file_count']),
                        'upload_date_obj' => new DateTime($trans_info['upload_date'])
                    );
                // $booking_stats_cache['by_group_id'][$my_group_id][$proposal_id]['transaction_list'][] = $transaction_id;
            }
        }
        // var_dump($booking_stats_cache);
        // exit();

        foreach($object_list as $proposal_id => $inst_groups){
            foreach($inst_groups as $inst_group_id => $record){
                $inst_group_id = $record['instrument_group_id'];
                $proposal_id = strval($record['proposal_id']);
                $earliest_date = clone($record['date_start']);
                $earliest_date->modify('-1 week');
                $latest_date = clone($record['date_finish']);
                $latest_date->modify('+3 weeks');
                //check the transaction record for matching entries
                $eus_objects[$proposal_id][$inst_group_id]['date_start'] = $record['date_start']->format('Y-m-d');
                $eus_objects[$proposal_id][$inst_group_id]['date_finish'] = $record['date_finish']->format('Y-m-d');
                if(isset($booking_stats_cache[$proposal_id][$inst_group_id])){
                    $transactions = $booking_stats_cache[$proposal_id][$inst_group_id]['transaction_list'];
                    foreach($transactions as $upload_date => $txn_entries){
                        foreach($txn_entries as $txn_entry){
                            if($txn_entry['upload_date_obj'] >= $earliest_date && $txn_entry['upload_date_obj'] <= $latest_date){
                                $eus_objects[$proposal_id][$inst_group_id]['file_count'] += $txn_entry['file_count'];
                            }
                        }

                    }
                }

                // $booking_stats_cache[$proposal_id][$inst_group_id]['booking_count'] += 1;
                // $booking_stats_cache['by_group_id'][$inst_group_id][$proposal_id]['booking_count'] += 1;
                // if(!in_array($record['instrument_id'], $booking_stats_cache[$proposal_id][$inst_group_id]['instruments_scheduled'])){
                //     $booking_stats_cache[$proposal_id][$inst_group_id]['instruments_scheduled'][]
                //         = $record['instrument_id'];
                // }
                // if(!in_array($record['instrument_id'], $booking_stats_cache['by_group_id'][$inst_group_id][$proposal_id]['instruments_scheduled'])){
                //     $booking_stats_cache['by_group_id'][$inst_group_id][$proposal_id]['instruments_scheduled'][]
                //         = $record['instrument_id'];
                // }



                // if(!in_array($record['instrument_id'], $booking_stats[$inst_group_id]['instruments_scheduled'])){
                //     $booking_stats[$inst_group_id]['instruments_scheduled'][] = $record['instrument_id'];
                // }
                // // $start_time = new DateTime($record['date_start']);
                // // $end_time = new DateTime($record['date_finish']);
                // // $start_time->modify('-1 week');
                // // $end_time->modify('+3 weeks');
                // $url_args_array = array(
                //     'instrument_group_id' => $record['instrument_group_id'],
                //     'proposal_id' => $record['proposal_id'],
                //     'start_time' => $start_time->format('Y-m-d'),
                //     'end_time' => $end_time->format('Y-m-d')
                // );
                // $url = "{$this->metadata_url_base}/transactioninfo/multisearch?";
                // $url .= http_build_query($url_args_array, '', '&');
                // $transactions_list_query = Requests::get($url, array('Accept' => 'application/json'));
                // if($transactions_list_query->status_code == 200){
                //     $transactions_list = json_decode($transactions_list_query->body, TRUE);
                //     $instrument_map = array();
                //     // foreach($transactions_list a
                //     $updates = array(
                //         'data_present' => $transactions_list ? TRUE : FALSE,
                //         'transaction_list' => $transactions_list,
                //     );
                //     foreach($transactions_list as $trans_id => $trans_info){
                //         $booking_stats[$inst_group_id]['data_file_count'] += $trans_info['file_count'];
                //     }
                //     $eus_objects[$object_id]['bookings'][$booking_id] = array_merge($eus_objects[$object_id]['bookings'][$booking_id], $updates);
                // }
            }
            // $eus_objects[$object_id]['booking_stats'] = $booking_stats;
        }
        // return $booking_stats_cache;
        return $eus_objects;
    }

    public function earliest_latest_booking_periods(){
        $column_array = array(
            'DATE(MIN(DATE_START)) as earliest',
            'DATE(MAX(DATE_FINISH)) as latest'
        );
        $results_array = array(
            'earliest' => FALSE,
            'latest' => FALSE
        );
        $query = $this->eusDB->select($column_array)->from("ERS_BOOKING")->get();
        if($query && $query->num_rows() > 0){
            $results = $query->result_array();
            $result = array_pop($results);
        }
        return $result;
    }

}
