<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Shine_pdf_upd {

	var $version = '0.1';
	
	function __construct()
	{
		ee() =& get_instance();
	}
	
	function install()
	{
		ee()->load->dbforge();
		
		$data = array(
			'module_name' => 'Shine_pdf',
			'module_version' => $this->version,
			'has_cp_backend' => 'n',
			'has_publish_fields' => 'n'
		);
		
		ee()->db->insert('modules', $data);
		
		return TRUE;
	}
	
	function uninstall()
	{
		ee()->load->dbforge();
		ee()->db->select('module_id');
		
		$query = ee()->db->get_where('modules', array('module_name' => 'Shine_pdf'));
		ee()->db->where('module_id', $query->row('module_id'));
		ee()->db->delete('module_member_groups');
	
		ee()->db->where('module_name', 'Shine_pdf');
		ee()->db->delete('modules');
			
		return TRUE;
	}
	
	function update($current = '')
	{
		return TRUE;
	}

}
// END CLASS Shine_pdf_upd

/* End of file upd.shine_pdf.php */
/* Location: ./system/expressionengine/third_party/modules/shine_pdf/upd.shine_pdf.php */