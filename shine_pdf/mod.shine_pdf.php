<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

@ini_set("pcre.backtrack_limit","10000000");

if( ! class_exists('Channel'))
{
	require_once(PATH_MOD.'channel/mod.channel.php');
}

class Shine_pdf extends Channel {

	private $_debug = array("debug!");

	function __construct()
	{
		// Derive initial functions from Channel module
		parent::__construct();

		// Set the EE Cache Path? (hell you can override that)
		$this->cache_path = PATH_CACHE;

		//exist cache_path
		if(!is_dir($this->cache_path))
		{
			if(!mkdir($this->cache_path))
			{
				$this->_debug[] = 'Cannot create cache dir';
				return false;
			}
		}

		//load helper
		ee()->load->helper('file');
		ee()->load->helper('download');
	}
	
	/*
	 * Gather output to push to the _process_pdf function
	 */
	function make() {
	
		//cache param
		$this->is_cache = in_array(ee()->TMPL->fetch_param('cache', 'yes'), array('yes', 'y', 'on')) ? true : false;
		$this->is_debug = in_array(ee()->TMPL->fetch_param('debug', 'no'), array('yes', 'y', 'on')) ? true : false;

		// Grab our variables and arguments from template tag
		$this->params = array(
			'mode'					=> '',															// Best left alone
			'format'				=> ee()->TMPL->fetch_param('format', 'A4'),				// Page format - can be subverted using {...width="" height=""...} params
			'default_font_size'		=> ee()->TMPL->fetch_param('default_font_size', 11),		// Base page font size
			'default_font'			=> ee()->TMPL->fetch_param('default_font', 'Helvetica'),	// Base page font face
			'margin_left'			=> ee()->TMPL->fetch_param('margin_left', 15),				// Left page margin
			'margin_right'			=> ee()->TMPL->fetch_param('margin_right', 15),			// Right page margin
			'margin_top'			=> ee()->TMPL->fetch_param('margin_top', 16),				// Top page margin - begins below header
			'margin_bottom'			=> ee()->TMPL->fetch_param('margin_bottom', 16),			// Bottom page margin - begins from footer
			'margin_header'			=> ee()->TMPL->fetch_param('margin_header', 9),			// Margin from page to header
			'margin_footer'			=> ee()->TMPL->fetch_param('margin_footer', 9),			// Margin from page to footer
			'orientation'			=> ee()->TMPL->fetch_param('orientation', 'P'),			// Page orientation
			'margin_top_auto'		=> ee()->TMPL->fetch_param('margin_top_auto', 'no'),		// Automatically calculate top margin
			'margin_bottom_auto'	=> ee()->TMPL->fetch_param('margin_bottom_auto', 'no')		// Automatically calculate bottom margin
		);

		// Parse advanced conditionals within parsed {exp:channel:entries} markup
		$input = ee()->TMPL->advanced_conditionals( parent::entries() );
		
		// Parse any global variables that might be present in markup
		$input = ee()->TMPL->parse_globals( $input );

		$input = ee()->TMPL->parse_template_php( $input );
		//no result
		if($this->sql == '')
		{
			return ee()->TMPL->no_results();
		}

		//re-query
		$this->query = ee()->db->query($this->sql);
		
		// Clean tag data
		$this->_clean_tagdata($input);
		
		// Set custom width and height if applicable
		$this->_custom_width_height();
		
		// Set orientation if applicable
		$this->_set_orientation();
		
		// Process final output using mPDF
		$this->_process_pdf($this->params);
		
	}
	
	/*
	 * Process final output using mPDF through our EE PDF library
	 */
	private function _process_pdf() {
		
		// Get the EE PDF library
		ee()->load->library('ee_pdf');

		// is there something todo?
		if($this->query->num_rows() > 0)
		{
			$filename = $this->cache_path.$this->query->row('entry_id').'_'.$this->query->row('entry_date').'.pdf';

			//get the file if exist in the cache
			$info = get_file_info($filename);

			//is there any file
			if($info != false && $this->is_cache)
			{
				$filename = $info['server_path'];
				$this->_debug[] = 'Fetch PDF from cache';
			}
			else
			{	
				 ee()->ee_pdf->debug = true;

				// Push our previously-declared tag data to the library
				$this->pdf = ee()->ee_pdf->load($this->params);
				$this->pdf->showImageErrors = true;

				// Set automatic margins if needed
				$this->_set_auto_margins();
				
				// Set PDF header if applicable
				if(isset($this->header))
				{
					$this->pdf->SetHTMLHeader($this->header);
				}
				
				// Set PDF footer if applicable
				if(isset($this->footer))
				{
					$this->pdf->SetHTMLFooter($this->footer);
				}

				//split in chunks 
				//if(strlen($this->body) > 10000)
				//{
				
				

					//$chunks = $this->_split_chunk($this->body);
					//print_r($chunks);exit;
					$chunks = explode('</div>', $this->body);

					$this->pdf->WriteHTML('', 0, true, false); 
					if(!empty($chunks)) 
					{ 
						foreach($chunks as $chunk) 
						{ 
							$this->pdf->WriteHTML($chunk.'</div>', 0, false, false); 
						} 
					} 
					$this->pdf->WriteHTML('', 0, false, true); 
				//}
				//else
				//{
				//	$this->pdf->WriteHTML($this->body, 0);
				//}
				//
				

				//delete old cache files
				$old_files = glob($this->cache_path.$this->query->row('entry_id')."_*.pdf");
				if(!empty($old_files))
				{
					foreach ($old_files as $_filename) {
					    @unlink($_filename);
					    $this->_debug[] = 'Delete old cache file: '.$_filename;
					}
				}
				

				//create new file
				$this->pdf->Output($filename, 'F');
				$this->_debug[] = 'Create new cache file: '.$filename;
			}

		}

		//no entries
		else
		{
			$this->_debug[] = 'Cannot create PDF file';
			if($this->is_debug)
			{
				$this->_log($this->_debug);
			}
			exit;
		}

		//force to download
		$this->_debug[] = 'Force to download the file: '.$this->query->row('title').'.pdf';

		//force to download the file
		if(!$this->is_debug)
		{
			force_download( $this->query->row('title').'.pdf', file_get_contents($filename));
		}
		else
		{
			$this->_log($this->_debug);
		}
			
		exit;

	}


	/* 
	* proper unicode str_split 
	*/
	private function _split_chunk($content) {
	   //return preg_split('/(<[^>]*[^\/]>)/i', $content, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
	   //return preg_split('/\s+/', $content);
	   //return preg_split('/\s+(?![^<>]+>)/m', $content);
	   return preg_split('~(</?[\w][^>]*>)~', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	}
		
	/*
	 * Define what manner of automatic margining to use if auto margins are desired
	 */
	private function _set_auto_margins() {
		
		// Set option according to tag input
		switch ($this->params['margin_top_auto'])
		{
			case 'stretch' :
				$margin_auto = 'stretch';
			break;
			case 'pad' :
				$margin_auto = 'pad';
			break;
			default :
				$margin_auto = FALSE;
			break;
		}
		
		// Set mPDF option
		$this->pdf->setAutoTopMargin = $margin_auto;
		
		// Set option according to tag input
		switch ($this->params['margin_bottom_auto'])
		{
			case 'stretch' :
				$margin_auto = 'stretch';
			break;
			case 'pad' :
				$margin_auto = 'pad';
			break;
			default :
				$margin_auto = FALSE;
			break;
		}
		
		// Set mPDF option
		$this->pdf->setAutoBottomMargin = $margin_auto;

	}
	
	/*
	 * Set custom width and height of page if specified
	 */
	private function _custom_width_height() {
		
		$width	= ee()->TMPL->fetch_param('width', FALSE);
		$height	= ee()->TMPL->fetch_param('height', FALSE);
		
		// Set only if both width and height are declared, otherwise relegate to default format
		if(isset($width) && isset($height) && $width && $height)
		{
			$this->params['format'] = array($width, $height);
		}
		
	}
	
	/*
	 * Set landscape orientation if specified, otherwise default to portrait
	 */
	private function _set_orientation()
	{
		switch($this->params['orientation'])
		{
			case "l" :
				$this->params['orientation'] = 'L';
			break;
			case "L" :
				$this->params['orientation'] = 'L';
			break;
			case "landscape" :
				$this->params['orientation'] = 'L';
			break;
			case "Landscape" :
				$this->params['orientation'] = 'L';
			break;
			default :
				$this->params['orientation'] = 'P';
			break;
		}
		
		if( ! is_array($this->params['format']) && $this->params['orientation'] == 'L')
		{
			$this->params['format'] .= '-L';
		}
	}

	/*
	 * Find first instances of {pdf_header} and {pdf_footer}, parse and eliminate them
	 */
	private function _clean_tagdata($input) {

		// Set Header from {pdf_header} tag pair
		preg_match('/\{pdf_header\b[^}]*\}(.*?)\{\/pdf_header\}/ism',$input,$m);
		if(isset($m[1]))
		{
			$this->header = trim($m[1]);
		}
		
		unset($m);
		
		// Set Footer from {pdf_footer} tag pair
		preg_match('/\{pdf_footer\b[^}]*\}(.*?)\{\/pdf_footer\}/ism',$input,$m);
		if(isset($m[1]))
		{
			$this->footer = trim($m[1]);
		}
		
		// Remove {pdf_header} and {pdf_footer} from output
		$patterns = array(
			'/\{pdf_header\b[^}]*\}(.*?)\{\/pdf_header\}/ism',
			'/\{pdf_footer\b[^}]*\}(.*?)\{\/pdf_footer\}/ism'
		);
		
		$this->body = preg_replace($patterns,'',$input);

	}

	/**
	 * Log all messages
	 *
	 * @param array $logs The debug messages.
	 * @return void
	 */
	private function _log( $logs = array())
	{
		if(!empty($logs))
		{
			foreach ($logs as $log)
			{
				echo '&nbsp;&nbsp;***&nbsp;&nbsp;Shine PDF debug: ' . $log."<br />";
			}
		}
	}
	
}
// END CLASS Shine_pdf

/* End of file mod.shine_pdf.php */
/* Location: ./system/expressionengine/third_party/modules/shine_pdf/mod.shine_pdf.php */