<?php echo $header; ?><?php echo $column_left; ?>
<div id="content"><div class="container-fluid">
	<div class="page-header">
	    <h1>Mobile Menu</h1>
	    <ul class="breadcrumb">
		     <?php foreach ($breadcrumbs as $breadcrumb) { ?>
		      <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
		      <?php } ?>
	    </ul>
	  </div>
	  
	<link href='http://fonts.googleapis.com/css?family=Open+Sans:600,500,400' rel='stylesheet' type='text/css'>
	<script type="text/javascript" src="view/javascript/summernote/summernote.js"></script>
	<link href="view/javascript/summernote/summernote.css" rel="stylesheet" />
	<script type="text/javascript" src="view/javascript/summernote/opencart.js"></script>  
	
	<script type="text/javascript">
	$.fn.tabs = function() {
		var selector = this;
		
		this.each(function() {
			var obj = $(this); 
			
			$(obj.attr('href')).hide();
			
			$(obj).click(function() {
				$(selector).removeClass('selected');
				
				$(selector).each(function(i, element) {
					$($(element).attr('href')).hide();
				});
				
				$(this).addClass('selected');
				
				$($(this).attr('href')).show();
				
				return false;
			});
		});
	
		$(this).show();
		
		$(this).first().click();
	};
	</script>

	<?php if ($error_warning) { ?>
		<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
		  <button type="button" class="close" data-dismiss="alert">&times;</button>
		</div>
	<?php } elseif ($success) {  ?>
		<div class="alert alert-success"><i class="fa fa-exclamation-circle"></i> <?php echo $success; ?>
			<button type="button" class="close" data-dismiss="alert">&times;</button>
		</div>
	<?php } ?>
	
	<form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form">
		<div class="set-size" id="mobile_menu">
			<div class="content">
				<div>
					<div class="tabs clearfix">
						<!-- Tabs module -->
						<div id="tabs" class="htabs main-tabs">
							<?php $module_row = 1; ?>
							<?php foreach ($modules as $module) { ?>
							<a href="#tab-module-<?php echo $module_row; ?>" id="module-<?php echo $module_row; ?>">Module <?php echo $module_row; ?> &nbsp;<img src="view/image/module_template/delete-slider.png"  alt="" onclick="$('.vtabs a:first').trigger('click'); $('#module-<?php echo $module_row; ?>').remove(); $('#tab-module-<?php echo $module_row; ?>').remove(); return false;" /></a>
							<?php $module_row++; ?>
							<?php } ?>
							<span id="module-add">Add Module &nbsp;<img src="view/image/module_template/add.png" alt="" onclick="addModule();" /></span>
						</div>
						
						<?php $module_row = 1; ?>
						<?php foreach ($modules as $module) { ?>
						<div id="tab-module-<?php echo $module_row; ?>" class="tab-content">
							<table class="form" style="margin-bottom:10px">
							  <tr>
							    <td style="border:none;padding-top:7px">Type:</td>
							    <td style="border:none;padding-top:7px"><select name="mobile_menu_module[<?php echo $module_row; ?>][type]" class="select-type" id="<?php echo $module_row; ?>">
							    	<?php if (1 == $module['type']) { ?>
							    	<option value="1" selected="selected">Block</option>
							    	<?php } else { ?>
							    	<option value="1">Block</option>
							    	<?php } ?>
							    	<?php if (2 == $module['type']) { ?>
							    	<option value="2" selected="selected">HTML</option>
							    	<?php } else { ?>
							    	<option value="2">HTML</option>
							    	<?php } ?>
							      </select></td>
							  </tr>
							</table>
							<div id="language-<?php echo $module_row; ?>" class="htabs">
							  <?php foreach ($languages as $language) { ?>
							  <a href="#tab-language-<?php echo $module_row; ?>-<?php echo $language['language_id']; ?>"><img src="language/<?php echo $language['code']; ?>/<?php echo $language['code']; ?>.png" title="<?php echo $language['name']; ?>" /> <?php echo $language['name']; ?></a>
							  <?php } ?>
							</div>
							<?php foreach ($languages as $language) { ?>
							<div id="tab-language-<?php echo $module_row; ?>-<?php echo $language['language_id']; ?>">
							  <div class="block<?php echo $module_row; ?>" <?php if (2 == $module['type']) { echo 'style="display:none"'; } ?>>
								  <table class="form">
								    <tr>
								      <td>Block heading:</td>
								      <td><input type="text" value="<?php echo isset($module['block_heading'][$language['language_id']]) ? $module['block_heading'][$language['language_id']] : ''; ?>" name="mobile_menu_module[<?php echo $module_row; ?>][block_heading][<?php echo $language['language_id']; ?>]" style="width:250px"></td>
								    </tr>
								    <tr>
								      <td>Block content:</td>
								      <td><textarea name="mobile_menu_module[<?php echo $module_row; ?>][block_content][<?php echo $language['language_id']; ?>]" id="block-content-<?php echo $module_row; ?>-<?php echo $language['language_id']; ?>"><?php echo isset($module['block_content'][$language['language_id']]) ? $module['block_content'][$language['language_id']] : ''; ?></textarea></td>
								    </tr>
								  </table>	
							  </div>
							  <div class="html<?php echo $module_row; ?>" <?php if (2 != $module['type']) { echo 'style="display:none"'; } ?>>
								  <table class="form">
								    <tr>
								      <td>HTML:</td>
								      <td><textarea name="mobile_menu_module[<?php echo $module_row; ?>][html][<?php echo $language['language_id']; ?>]" class="html"><?php echo isset($module['html'][$language['language_id']]) ? $module['html'][$language['language_id']] : ''; ?></textarea></td>
								    </tr>
								  </table>
							  </div>
							</div>
							
							<script type="text/javascript">
								$('#html-<?php echo $module_row; ?>-<?php echo $language['language_id']; ?>, #block-content-<?php echo $module_row; ?>-<?php echo $language['language_id']; ?>').summernote({
									height: 300
								});
							</script>
							<?php } ?>
							<table class="form">
							  <tr>
							    <td>Layout:</td>
							    <td><select name="mobile_menu_module[<?php echo $module_row; ?>][layout_id]">
							    	<?php if (99999 == $module['layout_id']) { ?>
							    	<option value="99999" selected="selected">All pages</option>
							    	<?php } else { ?>
							    	<option value="99999">All pages</option>
							    	<?php } ?>
							        <?php foreach ($layouts as $layout) { ?>
							        <?php if ($layout['layout_id'] == $module['layout_id']) { ?>
							        <option value="<?php echo $layout['layout_id']; ?>" selected="selected"><?php echo $layout['name']; ?></option>
							        <?php } else { ?>
							        <option value="<?php echo $layout['layout_id']; ?>"><?php echo $layout['name']; ?></option>
							        <?php } ?>
							        <?php } ?>
							      </select></td>
							  </tr>
							  <tr>
							    <td>Position:</td>
							    <td><select name="mobile_menu_module[<?php echo $module_row; ?>][position]">
							    	<?php if ($module['position'] == 'mobile_menu') { ?>
							    	<option value="mobile_menu" selected="selected">Mobile menu</option>
							    	<?php } else { ?>
							    	<option value="mobile_menu">Mobile menu</option>
							    	<?php } ?>
							      </select></td>
							  </tr>
							  <tr>
							    <td>Status:</td>
							    <td><select name="mobile_menu_module[<?php echo $module_row; ?>][status]">
							        <?php if ($module['status']) { ?>
							        <option value="1" selected="selected">Enabled</option>
							        <option value="0">Disabled</option>
							        <?php } else { ?>
							        <option value="1">Enabled</option>
							        <option value="0" selected="selected">Disabled</option>
							        <?php } ?>
							      </select></td>
							  </tr>
							  <tr>
							    <td>Sort Order:</td>
							    <td><input type="text" name="mobile_menu_module[<?php echo $module_row; ?>][sort_order]" value="<?php echo $module['sort_order']; ?>" size="3" /></td>
							  </tr>
							</table>
						</div>
						<?php $module_row++; ?>
						<?php } ?>
					</div>
					
					<!-- Buttons -->
					<div class="buttons"><input type="submit" name="button-save" class="button-save" value=""></div>
				</div>
			</div>
		</div>
	</form>
</div>
<script type="text/javascript"><!--
$('.main-tabs a').tabs();
//--></script> 

<script type="text/javascript"><!--
<?php $module_row = 1; ?>
<?php foreach ($modules as $module) { ?>
$('#language-<?php echo $module_row; ?> a').tabs();
<?php $module_row++; ?>
<?php } ?> 
//--></script> 

<script type="text/javascript"><!--
<?php $module_row = 1; ?>
<?php foreach ($modules as $module) { ?>
<?php $module_row++; ?>
<?php } ?>
//--></script> 

<script type="text/javascript">
$(document).ready(function() {
	
	$('#mobile_menu').on('change', 'select.select-type', function () {
		var id_module = $(this).attr("id");
		$("#" + id_module +" option:selected").each(function() {
			if($(this).val() == 1) {
				$(".html" + id_module + "").hide();
				$(".block" + id_module + "").show();
			} else {
				$(".html" + id_module + "").show();
				$(".block" + id_module + "").hide();
			}
		});
	});
	
});
</script>

<script type="text/javascript"><!--
var module_row = <?php echo $module_row; ?>;

function addModule() {	
	html  = '<div id="tab-module-' + module_row + '" class="tab-content">';

		html += '	<table class="form" style="margin-bottom:10px">';
		html += '		<tr>';
		html += '			<td style="border:none;padding-top:7px">Type:</td>';
		html += '			<td style="border:none;padding-top:7px">';
		html += '				<select name="mobile_menu_module[' + module_row + '][type]" class="select-type" id="' + module_row + '">';
		html += '					<option value="1" selected="selected">Block</option>';
		html += '					<option value="2">HTML</option>';
		html += '				</select>';
		html += '			</td>';
		html += '		</tr>';
		html += '   </table>';
		
		html += '  <div id="language-' + module_row + '" class="htabs">';
	    <?php foreach ($languages as $language) { ?>
	    html += '    <a href="#tab-language-'+ module_row + '-<?php echo $language['language_id']; ?>"><img src="language/<?php echo $language['code']; ?>/<?php echo $language['code']; ?>.png" title="<?php echo $language['name']; ?>" /> <?php echo $language['name']; ?></a>';
	    <?php } ?>
		html += '  </div>';
	
		<?php foreach ($languages as $language) { ?>
		html += '    <div id="tab-language-'+ module_row + '-<?php echo $language['language_id']; ?>">';
		
		html += '	 <div class="block' + module_row + '">';
		html += '	   <table class="form">';
		html += '			<tr>';
		html += '				<td>Block heading:</td>';
		html += '				<td><input type="text" value="" name="mobile_menu_module[' + module_row + '][block_heading][<?php echo $language['language_id']; ?>]" style="width:250px"></td>';
		html += '			</tr>';
		html += '			<tr>';
		html += '				<td>Block content:</td>';
		html += '				<td><textarea name="mobile_menu_module[' + module_row + '][block_content][<?php echo $language['language_id']; ?>]" id="block-content-' + module_row + '-<?php echo $language['language_id']; ?>"></textarea></td>';
		html += '			</tr>';
		html += '	   </table>';
		html += '	 </div>';
		
		html += '	 <div class="html' + module_row + '" style="display:none">';
		html += '      <table class="form">';
		html += '        <tr>';
		html += '          <td>HTML:</td>';
		html += '          <td><textarea name="mobile_menu_module[' + module_row + '][html][<?php echo $language['language_id']; ?>]" class="html"></textarea></td>';
		html += '        </tr>';
		html += '      </table>';
		html += '	  </div>';
		
		html += '    </div>';
		<?php } ?>
	
		html += '  <table class="form">';
		html += '    <tr>';
		html += '      <td>Layout:</td>';
		html += '      <td><select name="mobile_menu_module[' + module_row + '][layout_id]">';
		html += '           <option value="99999">All pages</option>';
		<?php foreach ($layouts as $layout) { ?>
		html += '           <option value="<?php echo $layout['layout_id']; ?>"><?php echo addslashes($layout['name']); ?></option>';
		<?php } ?>
		html += '      </select></td>';
		html += '    </tr>';
		html += '    <tr>';
		html += '      <td>Position:</td>';
		html += '      <td><select name="mobile_menu_module[' + module_row + '][position]">';
		html += '       		<option value="mobile_menu">Mobile menu</option>';
		html += '      </select></td>';
		html += '    </tr>';
		html += '    <tr>';
		html += '      <td>Status:</td>';
		html += '      <td><select name="mobile_menu_module[' + module_row + '][status]">';
		html += '        <option value="1">Enabled</option>';
		html += '        <option value="0">Disabled</option>';
		html += '      </select></td>';
		html += '    </tr>';
		html += '    <tr>';
		html += '      <td>Sort Order:</td>';
		html += '      <td><input type="text" name="mobile_menu_module[' + module_row + '][sort_order]" value="" size="3" /></td>';
		html += '    </tr>';
		html += '  </table>'; 
	html += '</div>';
	
	$('.tabs').append(html);
	
	$('#language-' + module_row + ' a').tabs();

	$('#module-add').before('<a href="#tab-module-' + module_row + '" id="module-' + module_row + '">Module ' + module_row + ' &nbsp;<img src="view/image/module_template/delete-slider.png" alt="" onclick="$(\'.vtabs a:first\').trigger(\'click\'); $(\'#module-' + module_row + '\').remove(); $(\'#tab-module-' + module_row + '\').remove(); return false;" /></a>');
	
	$('.main-tabs a').tabs();
	
	$('#module-' + module_row).trigger('click');
	
	<?php foreach ($languages as $language) { ?>
		$('#html-' + module_row + '-<?php echo $language['language_id']; ?>, #block-content-<?php echo $module_row; ?>-<?php echo $language['language_id']; ?>').summernote({
			height: 300
		});
	<?php } ?>
	
	module_row++;
}
//--></script> 
<?php echo $footer; ?>