<?php

if(class_exists('BM_PDF_to_Content')) return;
	
class BM_PDF_to_Content {
	
	var $debug = false;
	
	var $admin_page = 'pdf-to-content';
	var $pdf2text;
	var $uploads_dir;
	
	var $settings = array();
	var $generators = array();
	var $formats = array();
	
	var $media_icon;
	
	public $errors = array();
	
	/**
	 * BM_PDF_to_Content::__construct()
	 * 
	 * @return void
	 */
	function __construct(){
		
	}
	
	/**
	 * BM_PDF_to_Content::initialize()
	 * 
	 * @return void
	 */
	function initialize(){
		add_action( 'init', array($this, '_init'));
	}
	
	/**
	 * BM_PDF_to_Content::_init()
	 * 
	 * @return void
	 */
	function _init(){
		
		ini_set('memory_limit', '-1');
		set_time_limit(600); // 10 minutes
		
		$this->settings = $this->get_settings();
		
		$this->generators = array(
			'imagick' => array(
				'name' => 'ImageMagick',
				'description' => __('The most ideal method. Uses PHP\'s built in imagick class. <a href="http://php.net/manual/en/book.imagick.php" target="_blank">More Information</a>')
			),
			'gs_convert' => array(
				'name' => 'Convert (GhostScript)',
				'description' => __('This executes "convert" command, probably the most compatible method after ImageMagick.'),
				'fields' => array(
					'path' => array(
						'label' => 'Path to convert (optional)',
						'type' => 'text'
					)
				)
			),
			'gs' => array(
				'name' => 'GhostScript',
				'description' => __('If ImageMagick and Convert don\'t work, this attempts to use the "gs" command. Requires GhostScript installed on your server.'),
				'fields' => array(
					'path' => array(
						'label' => 'Path to GhostScript (optional)',
						'type' => 'text'
					)
				)
			),
			'convertapi' => array(
				'name' => 'ConvertAPI',
				'description' => __('Uses <a href="http://www.convertapi.com/" target="_blank">convertapi.com</a>\'s PDF2Image API (<a href="http://www.convertapi.com/pdf-image-api" target="_blank">convertapi.com/pdf-image-api</a>) to convert PDFs to images. This requires an account with an API key. <a href="http://www.convertapi.com/prices" target="_blank">Go here for more information.</a>'),
				'fields' => array(
					'api_key' => array(
						'label' => 'API Key',
						'type' => 'text'
					)
				)
			)
		);
		
		$this->formats = array(
			'png' => 'PNG',
			'jpg' => 'JPEG',
			'gif' => 'GIF',
			'bmp' => 'BMP'
		);
		
		if(is_admin()){
			
			if(class_exists('WP_GitHub_Updater')){
				$config = array(
					'slug' => 'bmoney-pdf-to-content/bmoney-pdf-to-content.php', // this is the slug of your plugin
					'proper_folder_name' => 'bmoney-pdf-to-content', // this is the name of the folder your plugin lives in
					'api_url' => 'https://api.github.com/solepixel/bmoney-pdf-to-content', // the github API url of your github repo
					'raw_url' => 'https://raw.github.com/solepixel/bmoney-pdf-to-content/master/', // the github raw url of your github repo
					'github_url' => 'https://github.com/solepixel/bmoney-pdf-to-content', // the github url of your github repo
					'zip_url' => 'https://github.com/solepixel/bmoney-pdf-to-content/archive/master.zip', // the zip url of the github repo
					'sslverify' => false, // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
					'requires' => '3.0', // which version of WordPress does your plugin require?
					'tested' => '3.5', // which version of WordPress is your plugin tested up to?
					'readme' => 'README.md', // which file to use as the readme for the version number
					'access_token' => '', // Access private repositories by authorizing under Appearance > Github Updates when this example plugin is installed
				);
				new WP_GitHub_Updater($config);
			}
			
			wp_register_script('bmptc-admin', BMPTC_DIR.'/js/admin.js', array('jquery'), BMPTC_VERSION);
			wp_register_style('bmptc-admin', BMPTC_DIR.'/css/admin.css', array(), BMPTC_VERSION);
			
			wp_enqueue_style('bmptc-admin');
			
			add_action('admin_menu', array($this, '_admin_menu'));
			
			require_once(BMPTC_PATH.'/classes/pdf2text.class.php');
			$this->pdf2text = new PDF2Text();
			$this->uploads_dir = wp_upload_dir();
			
			add_action('add_attachment', array($this, 'add_pdf_text'));
			add_action('add_attachment', array($this, 'add_pdf_thumbnail'));
			
			add_action('delete_attachment', array($this, 'delete_files'));
			
			add_action('admin_init', array($this, 'setup_metaboxes'));
			add_filter('wp_mime_type_icon', array($this, 'render_media_icon'), 10, 3);
			add_filter( 'icon_dir', array($this, 'change_icon_dir') );
		
			foreach($this->generators as $k => $gen){
				add_action('bmptc_thumbnail_'.$k, array($this, 'thumb_'.$k), 10, 4);
			}
			
			$this->generators = apply_filters('bmptc_thumbnail_generators', $this->generators);
			$this->formats = apply_filters('bmptc_thumbnail_formats', $this->formats);
		}
		
	}
	
	/**
	 * BM_PDF_to_Content::_admin_menu()
	 * 
	 * @return void
	 */
	function _admin_menu(){
		add_submenu_page('upload.php', BMPTC_PI_NAME, BMPTC_PI_NAME, 8, $this->admin_page, array($this, '_settings_page'));
	}
	
	/**
	 * BM_PDF_to_Content::get_settings()
	 * 
	 * @return
	 */
	function get_settings(){
		return get_option(BMPTC_OPT_PREFIX.'settings', array(
			BMPTC_OPT_PREFIX.'thumbnail_generator' => '',
			BMPTC_OPT_PREFIX.'thumbnail_quality' => '90'
		));
	}
	
	/**
	 * BM_PDF_to_Content::_settings_updated()
	 * 
	 * @return void
	 */
	function _settings_updated(){
		 echo '<div class="updated"><p>Your settings have been updated.</p></div>';
	}
	
	/**
	 * BM_PDF_to_Content::_settings_page()
	 * 
	 * @return void
	 */
	function _settings_page(){
		wp_enqueue_script('bmptc-admin');
		
		if(isset($_POST) && count($_POST) > 0){
			foreach($_POST as $k => $v){
				if(substr($k, 0, strlen(BMPTC_OPT_PREFIX)) == BMPTC_OPT_PREFIX){
					$this->settings[$k] = sanitize_text_field($v);
				}
			}
			update_option(BMPTC_OPT_PREFIX.'settings', $this->settings);
			add_action('admin_notices', array($this, '_settings_updated'));
		}
		
		include(BMPTC_PATH.'/admin/settings.php');
	}
	
	/**
	 * BM_PDF_to_Content::setup_metaboxes()
	 * 
	 * @return void
	 */
	function setup_metaboxes(){
		if($this->settings[BMPTC_OPT_PREFIX.'thumbnail_generator']){
			add_meta_box('bmptc_thumb_preview', __('Media Preview'), array($this, '_metabox_preview'), 'attachment', 'side');
		}
	}
	
	/**
	 * BM_PDF_to_Content::_metabox_preview()
	 * 
	 * @return void
	 */
	function _metabox_preview(){
		global $post;
		$media_icon = get_post_meta($post->ID, '_media_icon_url', true);
		if($media_icon){
			echo '<img src="'.$media_icon.'" style="width:100%" />';
		} else {
			echo 'No preview available.';
		}
	}
	
	/**
	 * BM_PDF_to_Content::add_pdf_text()
	 * 
	 * @param mixed $post_id
	 * @return void
	 */
	function add_pdf_text($post_id){
		$attachment = get_post($post_id);
		if($attachment->post_mime_type == 'application/pdf'){
			$pdf = get_attached_file($post_id);
			if(file_exists($pdf)){
				wp_update_post(array(
					'ID' => $post_id,
					'post_content' => $this->_get_pdf_text($pdf)
				));
			}
		}
	}
	
	/**
	 * BM_PDF_to_Content::add_pdf_thumbnail()
	 * 
	 * @param mixed $post_id
	 * @return void
	 */
	function add_pdf_thumbnail($post_id){
		$attachment = get_post($post_id);
		if($attachment->post_mime_type == 'application/pdf'){
			$pdf = get_attached_file($post_id);
			if(file_exists($pdf)){
				$this->generate_thumbnail($pdf, $post_id);
			}
		}
	}
	
	/**
	 * BM_PDF_to_Content::_get_pdf_text()
	 * 
	 * @param mixed $src
	 * @return
	 */
	function _get_pdf_text($src){
		
		$pdf_text = '';
		
		$cmd = 'pdftotext '.escapeshellarg($src). ' 2>/dev/null';
		putenv("PATH=/usr/local/bin:/usr/bin:/bin");
		shell_exec($cmd);
		
		$txt_file = str_replace('.pdf','.txt', $src);
		if(file_exists($txt_file)){
			$pdf_text = file_get_contents($txt_file);
			unlink($txt_file);
		}
		
		if(!$pdf_text){
			$this->pdf2text->setFilename($src);
			$this->pdf2text->decodePDF();
			
			$pdf_text = $this->pdf2text->output();
			$this->pdf2text->decodedtext = '';
		}
		
		if($pdf_text){
			$pdf_text = utf8_encode($pdf_text);
			$pdf_text = str_replace("\n\n", "\n", $pdf_text);
		}
		
		return $pdf_text;
	}
	
	
	/**
	 * BM_PDF_to_Content::generate_thumbnail()
	 * 
	 * @param mixed $pdf
	 * @param mixed $post_id
	 * @return void
	 */
	function generate_thumbnail($pdf, $post_id){
		$conversion_method = $this->settings[BMPTC_OPT_PREFIX.'thumbnail_generator'];
		$conversion_format = $this->settings[BMPTC_OPT_PREFIX.'thumbnail_format'];
		$conversion_quality = $this->settings[BMPTC_OPT_PREFIX.'thumbnail_quality'];
		
		if(!$conversion_format){
			$conversion_format = 'jpg';
		}
		if(!$conversion_quality){
			$conversion_quality = 100;
		}
		
		if($conversion_method){
			do_action('bmptc_thumbnail_'.$conversion_method, $pdf, $post_id, $conversion_format, $conversion_quality);
		}
	}
	
	/**
	 * BM_PDF_to_Content::thumb_imagick()
	 * 
	 * @param mixed $pdf
	 * @param mixed $post_id
	 * @param mixed $conversion_format
	 * @param mixed $conversion_quality
	 * @return void
	 */
	function thumb_imagick($pdf, $post_id, $conversion_format, $conversion_quality){
		if(class_exists('imagick')){
			$filename = str_replace('.pdf','.'.$conversion_format,basename($pdf));
			$output = $this->uploads_dir['path'].'/'.$filename;
			
			try {
				$im = @new imagick($pdf.'[0]');
			} catch(ImagickException $e){
				// imagick didn't work...
			}
			
			if($im){
				$im->setImageFormat($conversion_format);
				$im->setImageCompressionQuality($conversion_quality);
				$im->writeImages($output);
				$im = NULL;
			}
		}
		
		if(file_exists($output)){
			// set attachment thumbnail
			$url = $this->uploads_dir['url'].'/'.$filename;
			update_post_meta($post_id, '_media_icon_url', $url);
		}
	}
	
	/**
	 * BM_PDF_to_Content::thumb_gs_convert()
	 * 
	 * @param mixed $pdf
	 * @param mixed $post_id
	 * @param mixed $conversion_format
	 * @param mixed $conversion_quality
	 * @return void
	 */
	function thumb_gs_convert($pdf, $post_id, $conversion_format, $conversion_quality){
		$output = str_replace('.pdf','.'.$conversion_format, $pdf);
		
		$convert_path = $this->settings[BMPTC_OPT_PREFIX.'gs_convert_path'] ? $this->settings[BMPTC_OPT_PREFIX.'gs_convert_path'] : 'convert';
		$cmd = $convert_path.' -quality '.$conversion_quality.' '.escapeshellarg($pdf.'[0]').' '.escapeshellarg($output);
		
		putenv("PATH=/usr/local/bin:/usr/bin:/bin");
		$response = shell_exec($cmd.' 2>&1');
		if($this->debug){
			update_post_meta($post_id, '_convert_command', $cmd);
		}
		if($response && $this->debug){
			update_post_meta($post_id, '_convert_command_output', $response);
		}
		
		if(file_exists($output)){
			// set attachment thumbnail
			$url = $this->replace_path_with_host($output);
			update_post_meta($post_id, '_media_icon_url', $url);
		}
	}
	
	/**
	 * BM_PDF_to_Content::thumb_gs()
	 * 
	 * @param mixed $pdf
	 * @param mixed $post_id
	 * @param mixed $conversion_format
	 * @param mixed $conversion_quality
	 * @return void
	 */
	function thumb_gs($pdf, $post_id, $conversion_format, $conversion_quality){
		$output = str_replace('.pdf','.'.$conversion_format, $pdf);
		
		$gs_path = $this->settings[BMPTC_OPT_PREFIX.'gs_path'] ? $this->settings[BMPTC_OPT_PREFIX.'gs_path'] : 'gs';
		$cmd = "$gs_path -q -dNOPAUSE -dBATCH -sDEVICE=jpeg -dFirstPage=1 -dLastPage=1 -sOutputFile=\"$output\" \"$pdf\"";
		
		putenv("PATH=/usr/local/bin:/usr/bin:/bin");
		$response = shell_exec($cmd.' 2>&1');
		if($this->debug){
			update_post_meta($post_id, '_gs_command', $cmd);
		}
		if($response && $this->debug){
			update_post_meta($post_id, '_gs_command_output', $response);
		}
		
		if(file_exists($output)){
			// set attachment thumbnail
			$url = $this->replace_path_with_host($output);
			update_post_meta($post_id, '_media_icon_url', $url);
		}
	}
	
	/**
	 * BM_PDF_to_Content::thumb_convertapi()
	 * 
	 * @param mixed $pdf
	 * @param mixed $post_id
	 * @param mixed $conversion_format
	 * @param mixed $conversion_quality
	 * @return void
	 */
	function thumb_convertapi($pdf, $post_id, $conversion_format, $conversion_quality){
		$filename = str_replace('.pdf','.'.$conversion_format, basename($pdf));
		
		$url = 'http://do.convertapi.com/Pdf2Image/json';
		$postdata =  array(
			'ApiKey' => $this->settings[BMPTC_OPT_PREFIX.'convertapi_api_key'],
			'OutputFormat' => $conversion_format,
			'OutputFileName' => $filename,
			'StoreFile' => true,
			'file'=>"@".$pdf
		);
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		$result = curl_exec($ch); 
		$headers = curl_getinfo($ch);
		curl_close($ch);
		
		$response = json_decode($result);
		
		/*
		InputFormat": "pdf",
		"OutputFormat": "png",
		"FileSize": 1343672,
		"FileUrl": "http://do.convertapi.com/download?ee13a54d-0c45-4de4-b159-6584a9b6cef7",
		"OutputFileName": "bryant-denny-directions1.zip"
		*/
		
		$zip_source = $this->uploads_dir['path'].'/'.$response->OutputFileName;
		$zip_url = $this->uploads_dir['url'].'/'.$response->OutputFileName;
		copy($response->FileUrl, $zip_source);
		
		$extract_path = str_replace('.zip','',$zip_source).'/';
		$extract_url = str_replace('.zip','',$zip_url).'/';
		$this->_extract_zip($zip_source, $extract_path);
		$converted_pdf = $this->_get_image_from_zip($extract_url, $response->OutputFileName);
		unlink($zip_source);
		
		update_post_meta($post_id, '_convertapi_unzip', $extract_url);
		
		if(file_exists($converted_pdf)){
			// set attachment thumbnail
			$url = str_replace($_SERVER['DOCUMENT_ROOT'], $this->uploads_dir['url'], $converted_pdf);
			update_post_meta($post_id, '_media_icon_url', $url);
		}
		
	}
	
	/**
	 * BM_PDF_to_Content::_get_image_from_zip()
	 * 
	 * @param mixed $zip_url
	 * @param mixed $structure
	 * @param integer $page
	 * @return
	 */
	function _get_image_from_zip($zip_url, $structure, $page=1){
		$image = $zip_url.str_replace('.png', '-page'.$page.'.png', $structure);
		$image = str_replace('.zip', '-page'.$page.'.png', $image);
		return $image;
	}
	
	
	/**
	 * BM_PDF_to_Content::_extract_zip()
	 * 
	 * @param string $zip_source
	 * @param string $destination
	 * @return
	 */
	function _extract_zip( $zip_source = '', $destination = '' ){
		$zipDir = $destination;
		
		if(!is_dir($zipDir)){
			mkdir($zipDir, 0777);
		}
		
		if(file_exists($zip_source)){
			/* please don't hate me for the all the @.... */
			$zip = @zip_open($zip_source);
			
			if ($zip){
				$num_files = 0;
				while ($zip_entry = @zip_read($zip)){
					/*
					
					#
					#  I can't figure out WTF this does, so I commented it out. It has something to do with
					#  extracting folders from zip files, but since we won't ever have folders, i commented
					#  this code out. Sorry :(
					#
					
					$completePath = $zipDir . dirname(zip_entry_name($zip_entry));
					$completeName = $zipDir . zip_entry_name($zip_entry);
					
					// Walk through path to create non existing directories
					// This won't apply to empty directories ! They are created further below
					if(!file_exists($completePath) && preg_match( '#^' . $destination .'.*#', dirname(zip_entry_name($zip_entry)) ) ){
						$tmp = '';
						foreach(explode('/',$completePath) AS $k){
							$tmp .= $k.'/';
							if(!file_exists($tmp) ){
								@mkdir($tmp, 0777);
							}
						}
					}*/
					
					$completeName = $zipDir.@zip_entry_name($zip_entry);
					
					if (@zip_entry_open($zip, $zip_entry, "r")){
						/*if(!file_exists($completeName)){
							$file = fopen($completeName, 'w') or die("can't open file");
							fclose($file);
						}*/
						#if( preg_match( '#^' . $destination .'.*#', dirname(zip_entry_name($zip_entry)) ) ){
							if ($fd = @fopen($completeName, 'w+')){
								@fwrite($fd, @zip_entry_read($zip_entry, @zip_entry_filesize($zip_entry)));
								@fclose($fd);
								$num_files++;
							} else {
								// We think this was an empty directory
								@mkdir($completeName, 0777);
							}
							@zip_entry_close($zip_entry);
						#}
					}
				}
				@zip_close($zip);
				return $num_files;
			}
			
			zip_close($zip);
			return false;
		}
		return false;
	}
	
	/**
	 * BM_PDF_to_Content::render_media_icon()
	 * 
	 * @param mixed $icon
	 * @param mixed $mime
	 * @param mixed $post_id
	 * @return
	 */
	function render_media_icon($icon, $mime, $post_id){
		$media_icon = get_post_meta($post_id, '_media_icon_url', true);
		
		if($media_icon){
			$handle = curl_init($media_icon);
			curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
			$response = curl_exec($handle);
			$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			curl_close($handle);
			if($httpCode == 200) {
				$this->media_icon = $media_icon;
			    return $media_icon;
			}
		}
		return $icon;
	}
	
	function replace_path_with_host($path){
		$host = (isset($_SERVER['HTTPS'])) ? 'https://' : 'http://';
		$host .= $_SERVER['HTTP_HOST'];
		$url = str_replace($_SERVER['DOCUMENT_ROOT'], $host, $path);
		return $url;
	}
	
	
	function replace_host_with_path($url){
		$host = (isset($_SERVER['HTTPS'])) ? 'https://' : 'http://';
		$host .= $_SERVER['HTTP_HOST'];
		$path = str_replace($host, $_SERVER['DOCUMENT_ROOT'], $url);
		return $path;
	}
	
	/**
	 * BM_PDF_to_Content::change_icon_dir()
	 * 
	 * @param mixed $dir
	 * @return
	 */
	function change_icon_dir($dir){
		if($this->media_icon){
			$image_dir = str_replace(basename($this->media_icon), '', $this->media_icon);
			$dir = $this->replace_host_with_path($image_dir);
			$this->media_icon = NULL;
		}
		return $dir;
	}
	
	
	/**
	 * BM_PDF_to_Content::delete_files()
	 * 
	 * @param mixed $attachment_id
	 * @return void
	 */
	function delete_files($attachment_id){
		$media_icon = get_post_meta($attachment_id, '_media_icon_url', true);
		$host = (isset($_SERVER['HTTPS'])) ? 'https://' : 'http://';
		$host .= $_SERVER['HTTP_HOST'];
		$file = str_replace($host, $_SERVER['DOCUMENT_ROOT'], $media_icon);
		if(file_exists($file)){
			unlink($file);
		}
		
		//TODO: delete folder generated by convertapi unzipping
	}
}
