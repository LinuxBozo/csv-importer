<?php
/*
Plugin Name: CSV Importer
Description: Import data as posts from a CSV file. <em>You can reach the author at <a href="mailto:d.v.kobozev@gmail.com">d.v.kobozev@gmail.com</a></em>.
Version: 0.4.0
Author: Denis Kobozev, Bryan Headrick, M. Adam Kendall
*/
/**
 * LICENSE: The MIT License {{{
 *
 * Copyright (c) <2009> <Denis Kobozev>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author    Denis Kobozev <d.v.kobozev@gmail.com>
 * @copyright 2009 Denis Kobozev
 * @license   The MIT License
 * }}}
 */

class CSVImporterPlugin {
    var $defaults = array(
        'csv_post_title'      => null,
        'csv_post_post'       => null,
        'csv_post_type'       => null,
        'csv_post_excerpt'    => null,
        'csv_post_date'       => null,
        'csv_post_tags'       => null,
        'csv_post_categories' => null,
        'csv_post_author'     => null,
        'csv_post_slug'       => null,
        'csv_post_parent'     => 0,

    );

    var $log = array();

    /**
     * Determine value of option $name from database, $default value or $params,
     * save it to the db if needed and return it.
     *
     * @param string $name
     * @param mixed  $default
     * @param array  $params
     * @return string
     */
    function process_option($name, $default, $params) {
        if (array_key_exists($name, $params)) {
            $value = stripslashes($params[$name]);
        } elseif (array_key_exists('_'.$name, $params)) {
            // unchecked checkbox value
            $value = stripslashes($params['_'.$name]);
        } else {
            $value = null;
        }
        $stored_value = get_option($name);
        if ($value == null) {
            if ($stored_value === false) {
                if (is_callable($default) &&
                    method_exists($default[0], $default[1])) {
                    $value = call_user_func($default);
                } else {
                    $value = $default;
                }
                add_option($name, $value);
            } else {
                $value = $stored_value;
            }
        } else {
            if ($stored_value === false) {
                add_option($name, $value);
            } elseif ($stored_value != $value) {
                update_option($name, $value);
            }
        }
        return $value;
    }

    /**
     * Plugin's interface
     *
     * @return void
     */
    function form() {
        $opt_draft = $this->process_option('csv_importer_import_as_draft',
            'publish', $_POST);
        $opt_cat = $this->process_option('csv_importer_cat', 0, $_POST);

        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            $this->post(compact('opt_draft', 'opt_cat'));
        }

        // form HTML {{{
?>

<div class="wrap">
	<h2>Import CSV</h2>
	<form class="add:the-list: validate" method="post" enctype="multipart/form-data">
		<!-- Import as draft -->
		<p>
			<input name="_csv_importer_import_as_draft" type="hidden" value="publish" />
			<label><input name="csv_importer_import_as_draft" type="checkbox" <?php if ('draft' == $opt_draft) { echo 'checked="checked"'; } ?> value="draft" /> Import posts as drafts</label>
		</p>
		<!-- File input -->
		<p><label for="csv_import">Upload file:</label><br/>
			<input name="csv_import" id="csv_import" type="file" value="" aria-required="true" /></p>
			<p class="submit"><input type="submit" class="button" name="submit" value="Import" /></p>
	</form>
	<h2>Standard Fields</h2>
	<ul>
		<li><code>csv_post_title</code> : title of the post</li>
		<li><code>csv_post_post</code> : body of the post</li>
		<li><code>csv_post_type</code> : one of either 'post', 'page', or your custom post type</li>
		<li><code>csv_post_excerpt</code> : small excerpt of you post contents</li>
		<li><code>csv_post_date</code> : date your post should be published</li>
		<li><code>csv_post_tags</code> : your post tags (comma separated)</li>
		<li><code>csv_post_categories</code> : See below (Post categories)</li>
		<li><code>csv_ctax_{taxonomy_name}</code> : See below (Custom taxonomies)</li>
		<li><code>csv_post_author</code> : numeric user id or login name. If not specified or user does not exist, the plugin will assign the posts to the user performing the import.</li>
		<li><code>csv_post_slug</code> : post slug used in permalinks</li>
		<li><code>csv_post_parent</code> : post parent id (numeric)</li>
		<li><code>csv_attachment_{attachment_name}</code> : See below (Attachments)</li>
		<li><code>csv_attachment_thumbnail</code> : See below (Attachments)</li>
	</ul>
    <h2>Post categories</h2>
    <p>A pipe (|) separated list of category names or ids. It's also possible to assign posts to non-existing subcategories, using > to denote category relationships, e.g. Animalia > Chordata > Mammalia. If any of the categories in the chain does not exist, the plugin will automatically create it. It's also possible to specify the parent category using an id, as in 42 > Primates > Callitrichidae, where 42 is an existing category id. To specify a category that has a greater than sign (>) in the name, use the HTML entity &amp;gt;

	<h2>Custom taxonomies</h2>
	<p>Once custom taxonomies are set up in your theme's functions.php file or by using a 3rd party plugin, <code>csv_ctax_{taxonomy_name}</code> columns can be used to assign imported data to the taxonomies.</p>

	<h3>Non-hierarchical Taxonomies</h3>
	<p>The syntax for non-hierarchical taxonomies is straightforward and is essentially the same as the <code>csv_post_tags</code> syntax.</p>

	<h3>Hierarchical taxonomies</h3>
	<p>The syntax for hierarchical taxonomies is also straightforward and is essentially the same as the <code>csv_post_categories</code> syntax.</p>

	<h2>Attachments</h2>
	<p>You can now add attachments by uploading the files via ftp and then including the full URL to the attachment file including images, documents or any other file type that WordPress supports. The format is <code>csv_attachment_{attachment_name}</code>.</p>
	<p>Also, if the column name is <code>csv_attachment_thumbnail</code>, then the attachment will be set as the post's featured image.</p>

	<h2>Custom/Meta Fields</h2>
	<p>All columns not beginning with <strong>csv_</strong> will be imported as postmeta</p>

	<h2>Serialized Data Support</h2>
	<p>Now supports serializing data. Format meta field as follows:</p>
	<pre>key::value </pre>
	<p>or</p>
	<pre>key::value[]key::value</pre>
</div><!-- end wrap -->

<?php
        // end form HTML }}}

    }

    function print_messages() {
        if (!empty($this->log)) {

        // messages HTML {{{
?>

<div class="wrap">
    <?php if (!empty($this->log['error'])): ?>

    <div class="error">

        <?php foreach ($this->log['error'] as $error): ?>
            <p><?php echo $error; ?></p>
        <?php endforeach; ?>

    </div>

    <?php endif; ?>

    <?php if (!empty($this->log['notice'])): ?>

    <div class="updated fade">

        <?php foreach ($this->log['notice'] as $notice): ?>
            <p><?php echo $notice; ?></p>
        <?php endforeach; ?>

    </div>

    <?php endif; ?>
</div><!-- end wrap -->

<?php
        // end messages HTML }}}

            $this->log = array();
        }
    }

    /**
     * Handle POST submission
     *
     * @param array $options
     * @return void
     */
    function post($options) {
        if (empty($_FILES['csv_import']['tmp_name'])) {
            $this->log['error'][] = 'No file uploaded, aborting.';
            $this->print_messages();
            return;
        }

        set_time_limit(120);
        require_once 'File_CSV_DataSource/DataSource.php';

        $time_start = microtime(true);
        $csv = new File_CSV_DataSource;
        $file = $_FILES['csv_import']['tmp_name'];
        $this->stripBOM($file);

        if (!$csv->load($file)) {
            $this->log['error'][] = 'Failed to load file, aborting.';
            $this->print_messages();
            return;
        }

        // pad shorter rows with empty values
        $csv->symmetrize();

        // WordPress sets the correct timezone for date functions somewhere
        // in the bowels of wp_insert_post(). We need strtotime() to return
        // correct time before the call to wp_insert_post().
        $tz = get_option('timezone_string');
        if ($tz && function_exists('date_default_timezone_set')) {
            date_default_timezone_set($tz);
        }

        $skipped = 0;
        $imported = 0;
        $comments = 0;

        foreach ($csv->connect() as $csv_data) {

            if ($post_id = $this->create_post($csv_data, $options)) {
                $imported++;
                $comments += $this->add_comments($post_id, $csv_data);
                $this->create_custom_fields($post_id, $csv_data);
                $this->add_attachments($post_id,$csv_data);
            } else {
                $skipped++;
            }
        }

        if (file_exists($file)) {
            @unlink($file);
        }

        $exec_time = microtime(true) - $time_start;

        if ($skipped) {
            $this->log['notice'][] = "<b>Skipped {$skipped} posts (most likely due to empty title, body and excerpt).</b>";
        }
        $this->log['notice'][] = sprintf("<b>Imported {$imported} posts and {$comments} comments in %.2f seconds.</b>", $exec_time);
        $this->print_messages();
    }

    function create_post($data, $options) {
        extract($options);

        $data = array_merge($this->defaults, $data);
        $type = $data['csv_post_type'] ? $data['csv_post_type'] : 'post';
        $valid_type = (function_exists('post_type_exists') &&
            post_type_exists($type)) || in_array($type, array('post', 'page'));

        if (!$valid_type) {
            $this->log['error']["type-{$type}"] = sprintf(
                'Unknown post type "%s".', $type);
        }

        $new_post = array(
            'post_title'   => convert_chars($data['csv_post_title']),
            'post_content' => wpautop(convert_chars($data['csv_post_post'])),
            'post_status'  => $opt_draft,
            'post_type'    => $type,
            'post_date'    => $this->parse_date($data['csv_post_date']),
            'post_excerpt' => convert_chars($data['csv_post_excerpt']),
            'post_name'    => $data['csv_post_slug'],
            'post_author'  => $this->get_auth_id($data['csv_post_author']),
            'tax_input'    => $this->get_taxonomies($data),
            'post_parent'  => $data['csv_post_parent'],

        );

        // pages don't have tags or categories
        if ('page' !== $type) {
            $new_post['tags_input'] = $data['csv_post_tags'];

            // Setup categories before inserting - this should make insertion
            // faster, but I don't exactly remember why :) Most likely because
            // we don't assign default cat to post when csv_post_categories
            // is not empty.
            $new_post['post_category'] = $this->get_categories($data);
        }

        // create!
        $id = wp_insert_post($new_post);

        return $id;
    }
    /**
     * Return id of first image that matches the passed filename
     * @param string $filename csv_post_image cell contents
     *
     */
    function get_image_id($filename){
        //try searching titles first
        $filename =  preg_replace('/\.[^.]*$/', '', $filename);
         $filename = strtolower(str_replace(' ','-',$filename));
         $args = array('post_type' => 'attachment','name'=>$filename);
        $results = get_posts($args);
        //$results = get_page_by_title($filename, ARRAY_A, 'attachment');
        if(count($results)==0) return;
         if(count($results)==1) return $results[0]->ID;
        elseif(count($results)>1) {
            foreach($results as $result){
            if(strpos($result->guid,$filename))
                    return $result->ID;
            }
        }


    }

    /**
     * Return an array of category ids for a post.
     *
     * @param string  $data csv_post_categories cell contents
     * @return array category ids
     */
    function get_categories($data) {
        return $this->create_terms('category', $data['csv_post_categories']);
    }

    /**
     * Parse taxonomy data from the file
     *
     * array(
     *      // hierarchical taxonomy name => ID array
     *      'my taxonomy 1' => array(1, 2, 3, ...),
     *      // non-hierarchical taxonomy name => term names string
     *      'my taxonomy 2' => array('term1', 'term2', ...),
     * )
     *
     * @param array $data
     * @return array
     */
    function get_taxonomies($data) {
        $taxonomies = array();
        foreach ($data as $k => $v) {
            if (preg_match('/^csv_ctax_(.*)$/', $k, $matches)) {
                $t_name = $matches[1];
                if ($this->taxonomy_exists($t_name)) {
                    $taxonomies[$t_name] = $this->create_terms($t_name, $data[$k]);
                } else {
                    $this->log['error'][] = "Unknown taxonomy {$t_name}";
                }
            }
        }
        return $taxonomies;
    }
     /**
     * Parse attachment data from the file
     *
     * @param int   $post_id
     * @param array $data
     * @return array
     */
    function add_attachments($post_id, $data){
       // $this->log['notice'][]= 'adding attachments for id#'. $post_id;
        $attachments = array();
        foreach ($data as $k => $v) {
                if (preg_match('/^csv_attachment_(.*)$/', $k, $matches)) {
                   // $this->log['notice'][] = 'Found this attachment: ' . $matches[1] . ' with this value:' . $data[$k];
                    $a_name = $matches[1];

                        $attachment[$a_name] = $data[$k];

                        if(preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $data[$k])) {
                            $url = $v;
                            $id = $this->download_attachment($data[$k],$post_id,$a_name);}
                        if($a_name == 'thumbnail' && $id<>''){
                            add_post_meta($post_id, '_thumbnail_id',$id);
                        }
                }
                else if($k=='csv_post_image'){
                    $id = $this->get_image_id($v);
                    if($id<>'') add_post_meta($post_id, '_thumbnail_id',$this->get_image_id($v));
                }
            }
            return $attachments;
    }

    /**
     * Download file from remote URL, save it to the Media Library, and return
     * the attachment id
     *
     * @param string $url
     * @param int  $post_id
     * @param string $desc
     * @return int
     */
    function download_attachment($url, $post_id, $desc){
         set_time_limit(10);
        $tmp = download_url( $url );
    	 if(strlen(trim($url))<5) return;

    	// Set variables for storage
    	// fix file filename for query strings
    	//preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG|wav|mp3|pdf)/', $file, $matches);
    	 $file_array = array(
            'name' => basename( $url ),
            'tmp_name' => $tmp
                 );


    	// If error storing temporarily, unlink
    	if ( is_wp_error( $tmp ) ) {
    		@unlink($file_array['tmp_name']);
    		$file_array['tmp_name'] = '';
    	}

    	// do the validation and storage stuff
    	$id = media_handle_sideload( $file_array, $post_id, $desc );

    	// If error storing permanently, unlink
    	if ( is_wp_error($id) ) {
                 $this->log['error'][] = $id->get_error_message() .' : ' . $url;
    		@unlink($file_array['tmp_name']);
    		return $id;
    	}
             //$this->log['notice'][] = 'Downloaded the file. Here\'s the id: ' . $id;

    	$src = wp_get_attachment_url( $id );
             //$this->log['notice'][] = 'Saved the file successfully! Here\'s the path: ' . $src ;
        return $id;
    }


    /**
     * Return an array of term IDs for hierarchical taxonomies or the original
     * string from CSV for non-hierarchical taxonomies. The original string
     * should have the same format as csv_post_tags.
     *
     * @param string $taxonomy
     * @param string $field
     * @return mixed
     */
    function create_terms($taxonomy, $field) {
        if (is_taxonomy_hierarchical($taxonomy)) {
            $term_ids = array();
            $items = array_map('trim', explode('|', $field));
            foreach ($items as $item) {
                if (is_numeric($item)) {
                    if (get_term($item, $taxonomy) !== null) {
                        $term_ids[] = $item;
                    } else {
                        $this->log['error'][] = "{$taxonomy} ID {$item} does not exist, skipping.";
                    }
                } else {
                    // item can be a single category name or a string such as
                    // Parent > Child > Grandchild
                    $parent_id = 0;
                    $categories = array_map('trim', explode('>', $item));
                    if (count($categories) > 1 && is_numeric($categories[0])) {
                        $parent_id = $categories[0];
                        if (get_term($parent_id, $taxonomy) !== null) {
                            // valid id, everything's ok
                            $categories = array_slice($categories, 1);
                        } else {
                            $this->log['error'][] = "{$taxonomy} ID {$parent_id} does not exist, skipping.";
                            continue;
                        }
                    }
                    foreach ($categories as $category) {
                        if ($category) {
                            $term = $this->term_exists($category, $taxonomy, $parent_id);
                            if ($term) {
                                $term_id = $term['term_id'];
                            } else {
                                $parent_info = array('parent' => $parent_id);
                                $term_info= wp_insert_term($category, $taxonomy, $parent_info);
                                if (!is_wp_error($term_info)) {
                                    $term_id = $term_info['term_id'];
                                }
                            }
                            $parent_id = $term_id ?: 0;
                        }
                    }
                    $term_ids[] = $term_id;
                }
            }
            delete_option($taxonomy . "_children");
            return $term_ids;
        } else {
            return $field;
        }
    }

    /**
     * Compatibility wrapper for WordPress term lookup.
     */
    function term_exists($term, $taxonomy = '', $parent = 0) {
        if (function_exists('term_exists')) { // 3.0 or later
            return term_exists($term, $taxonomy, $parent);
        } else {
            return is_term($term, $taxonomy, $parent);
        }
    }

    /**
     * Compatibility wrapper for WordPress taxonomy lookup.
     */
    function taxonomy_exists($taxonomy) {
        if (function_exists('taxonomy_exists')) { // 3.0 or later
            return taxonomy_exists($taxonomy);
        } else {
            return is_taxonomy($taxonomy);
        }
    }

    function add_comments($post_id, $data) {
        // First get a list of the comments for this post
        $comments = array();
        foreach ($data as $k => $v) {
            // comments start with cvs_comment_
            if (    preg_match('/^csv_comment_([^_]+)_(.*)/', $k, $matches) &&
                    $v != '') {
                $comments[$matches[1]] = 1;
            }
        }
        // Sort this list which specifies the order they are inserted, in case
        // that matters somewhere
        ksort($comments);

        // Now go through each comment and insert it. More fields are possible
        // in principle (see docu of wp_insert_comment), but I didn't have data
        // for them so I didn't test them, so I didn't include them.
        $count = 0;
        foreach ($comments as $cid => $v) {
            $new_comment = array(
                'comment_post_ID' => $post_id,
                'comment_approved' => 1,
            );

            if (isset($data["csv_comment_{$cid}_author"])) {
                $new_comment['comment_author'] = convert_chars(
                    $data["csv_comment_{$cid}_author"]);
            }
            if (isset($data["csv_comment_{$cid}_author_email"])) {
                $new_comment['comment_author_email'] = convert_chars(
                    $data["csv_comment_{$cid}_author_email"]);
            }
            if (isset($data["csv_comment_{$cid}_url"])) {
                $new_comment['comment_author_url'] = convert_chars(
                    $data["csv_comment_{$cid}_url"]);
            }
            if (isset($data["csv_comment_{$cid}_content"])) {
                $new_comment['comment_content'] = convert_chars(
                    $data["csv_comment_{$cid}_content"]);
            }
            if (isset($data["csv_comment_{$cid}_date"])) {
                $new_comment['comment_date'] = $this->parse_date(
                    $data["csv_comment_{$cid}_date"]);
            }

            $id = wp_insert_comment($new_comment);
            if ($id) {
                $count++;
            } else {
                $this->log['error'][] = "Could not add comment $cid";
            }
        }
        return $count;
    }

    function create_custom_fields($post_id, $data) {
        foreach ($data as $k => $v) {
            // anything that doesn't start with csv_ is a custom field
            if (!preg_match('/^csv_/', $k) && $v != '')  {
                 // if value is serialized unserialize it
                if (is_serialized($v) ) {
                    $v = unserialize($v);
                    // the unserialized array will be re-serialized with add_post_meta()
                } elseif (strpos($v,'::')) {
                    // import data and serialize it formatted as
                    // key::value[]key::value
                    $array = explode("[]",$v);

                    foreach ($array as $lineNum => $line) {
                        list($key, $value) = explode("::", $line);
                        $newArray[$key] = $value;
                    }
                    $v = $newArray;
                }
                add_post_meta($post_id, $k, $v);
            }
        }

    }

    function get_auth_id($author) {
        if (is_numeric($author)) {
            return $author;
        }
        $author_data = get_user_by('login', $author);
        return ($author_data) ? $author_data->ID : 0;
    }

    /**
     * Convert date in CSV file to 1999-12-31 23:52:00 format
     *
     * @param string $data
     * @return string
     */
    function parse_date($data) {
        $timestamp = strtotime($data);
        if (false === $timestamp) {
            return '';
        } else {
            return date('Y-m-d H:i:s', $timestamp);
        }
    }

    /**
     * Delete BOM from UTF-8 file.
     *
     * @param string $fname
     * @return void
     */
    function stripBOM($fname) {
        $res = fopen($fname, 'rb');
        if (false !== $res) {
            $bytes = fread($res, 3);
            if ($bytes == pack('CCC', 0xef, 0xbb, 0xbf)) {
                $this->log['notice'][] = 'Getting rid of byte order mark...';
                fclose($res);

                $contents = file_get_contents($fname);
                if (false === $contents) {
                    trigger_error('Failed to get file contents.', E_USER_WARNING);
                }
                $contents = substr($contents, 3);
                $success = file_put_contents($fname, $contents);
                if (false === $success) {
                    trigger_error('Failed to put file contents.', E_USER_WARNING);
                }
            } else {
                fclose($res);
            }
        } else {
            $this->log['error'][] = 'Failed to open file, aborting.';
        }
    }
}


function csv_admin_menu() {
    require_once ABSPATH . '/wp-admin/admin.php';
    $plugin = new CSVImporterPlugin;
    add_management_page('edit.php', 'CSV Importer', 'manage_options', __FILE__,
        array($plugin, 'form'));
}

add_action('admin_menu', 'csv_admin_menu');

?>
