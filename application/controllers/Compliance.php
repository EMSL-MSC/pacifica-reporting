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
        $this->load->model('Summary_api_model', 'summary');

     }
 }
