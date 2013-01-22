<div class="wrap">
	<?php screen_icon(); ?>
	<h2>PDF to Content Settings</h2>
	
	<?php do_action('admin_notices'); ?>
	
	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
		
		<?php settings_fields('pdf-to-content'); ?>
		
		<h3><?php _e('PDF Thumbnails'); ?></h3>
		<p>You can enable PDF Thumbnails by using one of the following services. Some are free but require software on your server. Others do not require any software, but have a fee.</p>
		
		<table class="form-table">
			<tr valign="top">
				<th scope="row">Thumbnail Generator</th>
				<td>
					<div class="thumbnail_generator gen_none">
						<input name="<?php echo BMPTC_OPT_PREFIX; ?>thumbnail_generator" type="radio" id="thumbgen_none" value=""<?php if(!isset($this->settings[BMPTC_OPT_PREFIX.'thumbnail_generator']) || !$this->settings[BMPTC_OPT_PREFIX.'thumbnail_generator']) echo ' checked="checked"'; ?> />
						<label for="thumbnail_none"><strong><?php _e('None'); ?></strong></label>
					</div>
					<?php foreach($this->generators as $k => $gen){
						$checked = isset($this->settings[BMPTC_OPT_PREFIX.'thumbnail_generator']) ? $k == $this->settings[BMPTC_OPT_PREFIX.'thumbnail_generator'] : false; ?>
						<div class="thumbnail_generator gen_<?php echo $k; ?>">
							<input name="<?php echo BMPTC_OPT_PREFIX; ?>thumbnail_generator" type="radio" id="thumbgen_<?php echo $k; ?>" value="<?php echo $k; ?>"<?php if($checked) echo ' checked="checked"'; ?> />
							<label for="thumbgen_<?php echo $k; ?>"><strong><?php echo $gen['name']; ?></strong></label>
							<?php if(isset($gen['description'])) echo ' &nbsp; <span class="description">'.$gen['description'].'</span>'; ?>
							<?php if(isset($gen['fields']) && is_array($gen['fields']) && count($gen['fields']) > 0){ ?>
								<ul class="<?php echo $k; ?>_conditional conditional<?php if($checked) echo ' enabled'; ?>">
									<?php foreach($gen['fields'] as $name => $field){
										$field_name = BMPTC_OPT_PREFIX.$k.'_'.$name;
										$value = isset($this->settings[$field_name]) ? $this->settings[$field_name] : $field['value']; ?>
										<li><label>
											<span class="label"><?php echo $field['label']; ?></span>
											<span class="field"><input type="<?php echo $field['type']; ?>" name="<?php echo $field_name; ?>" value="<?php echo $value; ?>" /></span>
										</label><div class="clear"><!-- .clear --></div></li>
									<?php } ?>
								</ul>
							<?php } ?>
						</div>
					<?php } ?>
				</td>
			</tr>
			
			<tr valign="top">
				<th scope="row">Thumbnail Format</th>
				<td>
					<?php foreach($this->formats as $k => $format){
						$checked = isset($this->settings[BMPTC_OPT_PREFIX.'thumbnail_format']) ? $k == $this->settings[BMPTC_OPT_PREFIX.'thumbnail_format'] : false; ?>
						<div class="thumbnail_format format_<?php echo $k; ?>">
							<input name="<?php echo BMPTC_OPT_PREFIX; ?>thumbnail_format" type="radio" id="thumbformat_<?php echo $k; ?>" value="<?php echo $k; ?>"<?php if($checked) echo ' checked="checked"'; ?> />
							<label for="thumbformat_<?php echo $k; ?>"><?php echo $format; ?></label>
						</div>
					<?php } ?>
				</td>
			</tr>
			
			<tr valign="top">
				<th scope="row">Thumbnail Quality</th>
				<td>
					<input name="<?php echo BMPTC_OPT_PREFIX; ?>thumbnail_quality" type="number" id="thumbquality" value="<?php echo $this->settings[BMPTC_OPT_PREFIX.'thumbnail_quality']; ?>" />
				</td>
			</tr>
			
		</table>
		
		
		
		<?php submit_button(); ?>
	</form>

</div>