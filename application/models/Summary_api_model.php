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
  *  Summary Model
  *
  *  The **Summary_model** class contains functionality for
  *  summarizing upload and activity data. It pulls data from
  *  both the MyEMSL and website_prefs databases
  *
  * @category CI_Model
  * @package  Pacifica-reporting
  * @author   Ken Auberry <kenneth.auberry@pnnl.gov>
  *
  * @license BSD https://opensource.org/licenses/BSD-3-Clause
  * @link    http://github.com/EMSL-MSC/Pacifica-reporting

  * @uses   EUS EUS Database access library
  * @access public
  */
class Summary_api_model extends CI_Model
{
    public $results;


    /**
     *  Class constructor
     *
     *  @author Ken Auberry <kenneth.auberry@pnnl.gov>
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->library('PHPRequests');
        $this->md_base_url = $this->config->item('metadata_server_base_url');
        $this->policy_base_url = $this->config->item('policy_server_base_url');
        $this->load->helper(array('item', 'time'));
        $this->results = array(
                          'transactions'   => array(),
                          'time_range'     => array(
                                               'start_time' => '',
                                               'end_time'   => '',
                                              ),
                          'day_graph'      => array(
                                               'by_date' => array(
                                                             'available_dates'         => array(),
                                                             'file_count'              => array(),
                                                             'file_volume'             => array(),
                                                             'file_volume_array'       => array(),
                                                             'transaction_count_array' => array(),
                                                            ),
                                              ),
                          'summary_totals' => array(
                                               'upload_stats'      => array(
                                                                       'proposal'   => array(),
                                                                       'instrument' => array(),
                                                                       'user'       => array(),
                                                                      ),
                                               'total_file_count'  => 0,
                                               'total_size_bytes'  => 0,
                                               'total_size_string' => "",
                                              ),
                         );

    }//end __construct()


    public function summarize_uploads($group_type, $id_list, $iso_start_date, $iso_end_date, $make_day_graph, $time_basis){
        //returns array that extracts to $start_date_object, $end_date_object, $start_time, $end_time
        extract(canonicalize_date_range($iso_start_date, $iso_end_date));
        $this->results['day_graph']['by_date']['available_dates'] = generate_available_dates(
            $start_date_object, $end_date_object
        );
        $this->results['summary_totals']['upload_stats'] = $this->_generate_summary_totals(
            $group_type, $id_list, $start_date, $end_date, $time_basis_type
        );

    }


    private function _generate_summary_totals($group_type, $id_list, $start_date, $end_date, $time_basis_type)
    {
        $transaction_url = "{$this->policy_base_url}/status/transactions/search/";
        $allowed_group_types = array('instrument', 'proposal', 'user');
        if(in_array($group_type, $allowed_group_types)){
            foreach($id in $id_list){
                $url_args_array = array(
                    $group_type => $id,
                    'start' => $start_date->format('Y-m-d H:i:s'),
                    'end' => $end_date->format('Y-m-d H:i:s')
                );
                $url = $transaction_url;
                $url .= http_build_query($url_args_array, '', '&');
                $query = Requests::get($url, array('Accept' => 'application/json'));
                $results = json_decode($query->body, TRUE);
            }
        }

    }

}
