<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ee_pdf {

	var $folder_name = 'shine_pdf';

	public function __construct()
	{
		// Require the mPDF library
		require_once __DIR__ . '/vendor/autoload.php';
	}
	
	/*
	 * Creates a CI handler for the mPDF object
	 */
	public function load($params)
	{
		// Gives us our final output
		return new \Mpdf\Mpdf($params);
	}
	
}