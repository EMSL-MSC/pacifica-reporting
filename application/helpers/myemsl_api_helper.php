<?php
/**
 * Pacifica
 *
 * Pacifica is an open-source data management framework designed
 * for the curation and storage of raw and processed scientific
 * data. It is based on the [CodeIgniter web framework](http://codeigniter.com).
 *
 *  The Pacifica-upload-status module provides an interface to
 *  the ingester status reporting backend, allowing users to view
 *  the current state of any uploads they may have performed, as
 *  well as enabling the download and retrieval of that data.
 *
 *  This file contains a number of common functions for retrieving
 *
 * PHP version 5.5
 *
 * @package Pacifica-upload-status
 *
 * @author  Ken Auberry <kenneth.auberry@pnnl.gov>
 * @license BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @link http://github.com/EMSL-MSC/Pacifica-reporting
 */

if(!defined('BASEPATH')) { exit('No direct script access allowed');
}

/**
 *  Directly retrieves user info from the MyEMSL EUS
 *  database clone
 *
 *  @param integer $eus_id user id of the person in question
 *
 *  @return array
 *
 *  @author Ken Auberry <kenneth.auberry@pnnl.gov>
 */
function get_user_details($eus_id)
{
    $CI =& get_instance();
    $CI->load->library('PHPRequests');
    // $md_url = $CI->config->item('metadata_url');
    $md_url = $CI->metadata_url_base;
    $query_url = "{$md_url}/userinfo/by_id/{$eus_id}";
    $query = Requests::get($query_url, array('Accept' => 'application/json'));
    $results_body = $query->body;

    return json_decode($results_body, TRUE);
}
