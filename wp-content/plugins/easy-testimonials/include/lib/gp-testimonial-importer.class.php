<?php
class TestimonialsPlugin_Importer
{
	var $root;
	
    public function __construct($root)
    {
		$this->root = $root;
	}
	
	//convert CSV to array
	private function csv_to_array($filename='', $delimiter=','){
		if(!file_exists($filename) || !is_readable($filename))
			return FALSE;

		$header = NULL;
		$data = array();
		
		if (($handle = fopen($filename, 'r')) !== FALSE)
		{
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
			{
				if(!$header){
					$header = $row;
				} else {
					$data[] = array_combine($header, $row);
				}
			}
			fclose($handle);
		}
		return $data;
	}
	
	//process data from CSV import
	private function import_testimonials_from_csv($testimonials_file){	
		//increase execution time before beginning import, as this could take a while
		set_time_limit(0);		
		
		//use current timestamp for batch number
		$batch_number = time();
		
		$testimonials = $this->csv_to_array($testimonials_file);
		
		foreach($testimonials as $testimonial){					
			//defaults
			$the_name = $the_body = '';

			if (isset ($testimonial['Title'])) {
				$the_name = $testimonial['Title'];
			}
			
			if (isset ($testimonial['Body'])) {
				$the_body = $testimonial['Body'];
			}	
			
			//look for a testimonial with the title and body
			//if not found, insert this one
			$postslist = get_page_by_title( $the_name, OBJECT, 'testimonial' );
			
			//if this is empty, a match wasn't found and therefore we are safe to insert
			if(empty($postslist)){				
				//insert the testimonials				
				$tags = array();
			   
				$post = array(
					'post_title'    => $the_name,
					'post_content'     => $the_body,
					'post_category' => array(1),  // custom taxonomies too, needs to be an array
					'tags_input'    => $tags,
					'post_status'   => 'publish',
					'post_type'     => 'testimonial',
					'post_author' => get_option('easy_t_testimonial_author', 1)
				);
			
				$new_id = wp_insert_post($post);
			   
				//defaults, in case certain data wasn't in the CSV			
				$client_name = isset($testimonial['Client Name']) ? $testimonial['Client Name'] : "";
				$position_location_other = isset($testimonial['Position / Location / Other']) ? $testimonial['Position / Location / Other'] : "";
				$location_product_other = isset($testimonial['Location / Product / Other']) ? $testimonial['Location / Product / Other'] : "";
				$rating = isset($testimonial['Rating']) ? $testimonial['Rating'] : "";
				$htid = isset($testimonial['HTID']) ? $testimonial['HTID'] : "";
			   
				update_post_meta( $new_id, '_ikcf_client', $client_name );
				update_post_meta( $new_id, '_ikcf_position', $position_location_other );
				update_post_meta( $new_id, '_ikcf_other', $location_product_other );
				update_post_meta( $new_id, '_ikcf_rating', $rating );
				update_post_meta( $new_id, '_ikcf_htid', $htid );
				update_post_meta( $new_id, '_ikcf_import_batch', $batch_number );
			   
				$inserted = true;
				echo "Successfully imported '{$the_name}'!\n";
			} else { //rejected as duplicate
				echo "Could not import {$the_name}; rejected as Duplicate\n";
			}
		}
		//die('t1');
	}
	
	//displays fields to allow user to upload and import a CSV of testimonials
	//if a file has been uploaded, this will dispatch the file to the import function
	public function csv_importer(){	
		
		// Load Importer API
		require_once ABSPATH . 'wp-admin/includes/import.php';

		if ( !class_exists( 'WP_Importer' ) ) {
			$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
			if ( file_exists( $class_wp_importer ) )
				require_once $class_wp_importer;
		}		
		
		if(empty($_FILES)){	
			//echo '<form method="POST" action="" enctype="multipart/form-data">';	
			echo "<p>Use the form below to upload your CSV file for importing.</p>";
			echo "<p><strong>Example CSV Data:</strong></p>";
			echo "<p><code>'Title','Body','Client Name','Position / Location / Other','Location / Product / Other','Rating','HTID'</code></p>";
			echo "<p><strong>Please Note:</strong> the first line of the CSV will need to match the text in the above example, for the Import to work.  Depending on your server settings, you may need to run the import several times if your script times out.</p>";

			echo '<div class="gp_upload_file_wrapper">';
				ob_start();					
				wp_import_upload_form( add_query_arg('step', 1) );
				$import_form_html = ob_get_contents();
				$import_form_html = str_replace('<form ', '<div data-gp-ajax-form="1" ', $import_form_html);
				$import_form_html = str_replace('</form>', '</div>', $import_form_html);
				
				// must remove this hidden "action" input, or the form will not 
				// save proerly (it will keep going to options.php)
				$import_form_html = str_replace('<input type="hidden" name="action" value="save" />', '', $import_form_html);
				ob_end_clean();					
				echo $import_form_html;
			echo '</div>';
			//echo '</form>';
		} else {
			$file = wp_import_handle_upload();
			
			echo '<h4>Log</h4>';
			echo '<textarea rows="20" class="import_response">';
			
			//if there is an error, output a message containing the error
			if ( isset( $file['error'] ) ) {
				echo "Sorry, there has been an error.\n";
				echo esc_html( $file['error'] ) . "\n";
			// if the file doesn't exists, output a message about it
			} else if ( ! file_exists( $file['file'] ) ) {
				echo "Sorry, there has been an error.\n";
				printf( "The export file could not be found at %s. It is likely that this was caused by a permissions problem.\n", esc_html( $file['file'] ) );
			// otherwise, if there is no error and the file exists, go ahead and process the file
			} else {			
				$fileid = (int) $file['id'];
				$file = get_attached_file($fileid);
				$this->import_testimonials_from_csv($file);
				
				echo "\nTestimonials successfully imported!\n";
			}
			
			echo '</textarea>';//close response
			
			echo '<p><a class="button-primary button" href="/wp-admin/admin.php?page=easy-testimonials-import-export" title="Import More Testimonials">Import More Testimonials</a></p>';
		}
	}
}