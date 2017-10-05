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

 defined('BASEPATH') OR exit('No direct script access allowed');
 require_once 'Baseline_api_controller.php';

 /**
  *  Group is a CI controller class that extends Baseline_controller
  *
  *  The *Compliance* class contains user-interaction logic for a set of CI pages.
  *  It interfaces with several different models to retrieve summary information
  *  for purposes of assuring compliance with DOE policy in regards to data
  *  retention and storage.
  *
  * @category Page_Controller
  * @package  Pacifica-reporting
  * @author   Ken Auberry <kenneth.auberry@pnnl.gov>
  *
  * @license BSD https://opensource.org/licenses/BSD-3-Clause
  * @link    http://github.com/EMSL-MSC/Pacifica-reporting

  * @see    https://github.com/EMSL-MSC/pacifica-reporting
  * @access public
  */
 class Compliance extends Baseline_api_controller
 {
     /**
      * [__construct description]
      * @author Ken Auberry <kenneth.auberry@pnnl.gov>
      */
     public function __construct()
     {
        parent::__construct();
        // $this->load->model('Summary_api_model', 'summary');
        $this->load->model('Compliance_model', 'compliance');
        $this->load->helper(
            ['network', 'theme', 'search_term', 'calendar', 'form']
        );
        $this->accepted_object_types = array('instrument', 'user', 'proposal');
        sort($this->accepted_object_types);
        $this->page_data['script_uris'] = array(
            '/resources/scripts/spinner/spin.min.js',
            '/resources/scripts/spinner/jquery.spin.js',
            '/resources/scripts/select2-4/dist/js/select2.js',
            '/resources/scripts/moment.min.js',
            '/resources/scripts/js-cookie/src/js.cookie.js',
            '/project_resources/scripts/compliance.js'

        );
        $this->page_data['css_uris'] = array(
            '/resources/scripts/select2-4/dist/css/select2.css',
            '/project_resources/stylesheets/combined.css',
            '/project_resources/stylesheets/selector.css',
            '/project_resources/stylesheets/compliance.css'
        );
        $this->page_data['load_prototype'] = FALSE;
        $this->page_data['load_jquery'] = TRUE;
        $this->last_update_time = get_last_update(APPPATH);
        $this->page_data['object_types'] = $this->accepted_object_types;
    }

    /**
     *
     * @method index
     *
     * @author Ken Auberry <kenneth.auberry@pnnl.gov>
     *
     * @return none
     */
    public function index($report_type = 'proposal')
    {
        $this->page_data['page_header'] = "Compliance Reporting";
        $this->page_data['script_uris'] = load_scripts($this->page_data['script_uris']);
        $this->page_data['css_uris'] = load_stylesheets($this->page_data['css_uris']);
        $earliest_latest = $this->compliance->earliest_latest_booking_periods();
        $js = "var earliest_available = '{$earliest_latest['earliest']}'; var latest_available = '{$earliest_latest['latest']}'";
        $this->page_data['js'] = $js;
        $this->page_data['object_type'] = $report_type;
        $this->load->view("data_compliance_report_view.html", $this->page_data);
    }

    public function get_report($object_type, $start_time, $end_time){
        if(!in_array($object_type, array('instrument', 'proposal'))){
            return false;
        }
        $t_first_day = new DateTime('first day of this month');
        $t_last_day = new DateTime('last day of this month');

        header('Content-Type: application/json');
        $start_time_obj = strtotime($start_time) ? new DateTime($start_time) : $t_first_day;
        $end_time_obj = strtotime($end_time) ? new DateTime($end_time) : $t_last_day;
        $eus_booking_records
            = $this->compliance->retrieve_active_proposal_list_from_eus($start_time_obj, $end_time_obj);

        // print(json_encode($eus_booking_records['by_proposal']));
        // exit();
        $group_name_lookup = $this->compliance->get_group_name_lookup();
        $mappings = $this->compliance->cross_reference_bookings_and_data($object_type, $eus_booking_records, $start_time_obj, $end_time_obj);
        ksort($mappings);

        // print(json_encode($mappings));
        // exit();
        $page_data = array(
            'results_collection' => $mappings,
            'group_name_lookup' => $group_name_lookup,
            'object_type' => $object_type,
            'start_date' => $start_time_obj->format('Y-m-d'),
            'end_date' => $end_time_obj->format('Y-m-d')
        );

        // print(json_encode($page_data));

        $this->load->view('object_types/compliance_reporting/reporting_table_proposal.html', $page_data);
    }
 }
