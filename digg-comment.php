<?php
/*
Plugin Name: Digg comments
Plugin URI: http://www.wordpress.org/extends/BETA
Description: A plugin enables your readers to digg/bury the comments without even a registration!
Version: 1.0
Author: Aw Guo
Author URI: http://www.ifgogo.com/
*/
class cmtdigg_digg {
    private $etime = 1; // Restrict a same IP for this time (in second)
    public $plugin_name = 'digg comments';
    public $plugin_path = '';
    public $plugin_options_path = '';

    public function __construct() {
    	// Get the paths
        $this->plugin_path = get_bloginfo('wpurl') . '/' . PLUGINDIR . '/' . plugin_basename(__FILE__);
        $this->plugin_options_path = dirname(plugin_basename(__FILE__)) . '/options.php';
    }

    /**
     * Accept AJAX request for voting / burying
     * @return void
     */
    public function ajax_digg() {
        global $wpdb;
        // Identify the plugin
        $cmtdiggaction = (isset($_GET['cmtdiggaction']) && ($_GET['cmtdiggaction'] == 'diggcomment')) ? TRUE : FALSE;
        // Get the comment ID
        $commentid = (isset($_GET['commentid']) && is_numeric($_GET['commentid'])) ? $_GET['commentid'] : FALSE;
        // Retrieve the action detail
        $diggAction = isset($_GET['diggAction']) ? $_GET['diggAction'] : NULL;
        	
        
        // Check if it is an AJAX request and providing a valid comment id, diggAction
        if ($this->isAjax() && $cmtdiggaction && $commentid && in_array($diggAction, array('UP' , 'DOWN'))) {
        	
        	//Check whether it is allowed to vote
            if ($this->check($commentid)) {
            	
                //Set colname for database updating - digg and bury
                if ($diggAction == 'UP') {
                    $colname = 'digg';
                } else {
                    $colname = 'bury';
                }
                
                // Add vote number
                $sql = 'UPDATE ' . $wpdb->comments . ' SET `' . $colname . '` = `' . $colname . '` + 1 WHERE comment_ID = ' . $commentid . ';';
                $wpdb->query($sql);
                
                // Record IP address
                $wpdb->insert($wpdb->prefix . 'diggc_log', array('cid' => $commentid , 'ip' => $this->getIp() , 'time' => $_SERVER['REQUEST_TIME']));

                // Purge expired IP address
                $etime = $_SERVER['REQUEST_TIME'] - $this->etime;
                $sql = "DELETE FROM `{$wpdb->prefix}diggc_log` WHERE time < {$etime}";
                $wpdb->query($sql);
                
                // Set cookie
                setcookie('cmtdiggdigg' . $commentid, 1, $_SERVER['REQUEST_TIME'] + 61104000);
                
                // Yes for a successful vote
                exit('yes');
            }
            // No for a failed vote
            exit('no');
        }
        return;
    }

    /**
     * Check whether we could vote for this comment
     *    judged by the COOKIE and IP restriction
     *
     * @param int $cid Comment ID
     * @return boolean Wether it is allowed to vote for this comment
     */
    public function check($cid) {
        global $wpdb;
        if (isset($_COOKIE['cmtdiggdigg' . $cid]) && $_COOKIE['cmtdiggdigg' . $cid]) {
            return FALSE;
        }
        $etime = $_SERVER['REQUEST_TIME'] - 3600;
        $sql = 'SELECT * FROM `' . $wpdb->prefix . 'diggc_log` WHERE `cid` = ' . $cid . ' AND `ip` = \'' . $this->getIp() . '\' AND `time` > ' . $etime;
        if ($wpdb->get_row($sql)) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Head injection for the comment digging system(js和css)
     *
     * @return void
     */
    public function head_include() {
        ?>
        <style>
			/*comment-digg*/
			.fL					{float:left}
			.fR					{float:right}
			.clearfix:after 	{content: ".";display: block;height: 0;clear: both;visibility: hidden}
			.clearfix 			{display: inline-block}
			* html .clearfix 	{height: 1%}
			.clearfix 			{display: block}
			.cd-wrapper{cursor:default;font-size:12px;font-family:Venderna;margin:7px 6px 4px}
			a.cd-votebtn{padding:2px;cursor:pointer}
			span.cd-votebtn{padding:2px;color:#333}
			.cd-votebar{padding:4px 8px 0 8px}
			.cd-votebar-table{width:90px; height:8px}
			.cd-votebar .bar {padding:0 1px}
        </style>
        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.2.6/jquery.min.js"></script>
		<script>
			 /* Awc - cookie manager - */
       	  jQuery.awc = function(name, value, options) {
		    if (typeof value != 'undefined') {
		        options = options || {};
		        if (value === null) {
		            value = '';
		            options.expires = -1;
		        }
		        options.expires = 10;
		        var expires = '';
		        var date = new Date();
		        date.setTime(date.getTime() + (options.expires * 24 * 60 * 60 * 1000));
		        expires = '; expires=' + date.toUTCString();
		        var path = '; path=/';
		        var domain = options.domain ? '; domain=' + (options.domain) : '';
		        var secure = options.secure ? '; secure' : '';
		        document.cookie = [name, '=', encodeURIComponent(value), expires, path, domain, secure].join('');
		    } else {
		        var cookieValue = null;
		        if (document.cookie && document.cookie != '') {
		            var cookies = document.cookie.split(';');
		            for (var i = 0; i < cookies.length; i++) {
		                var cookie = jQuery.trim(cookies[i]);
		                if (cookie.substring(0, name.length + 1) == (name + '=')) {
		                    cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
		                    break;
		                }
		            }
		        }
		        return cookieValue;
		    }
		};
			</script>
        
        <script>       
       window.onload = diggcommentinit;
       function diggcommentinit(){
       	  
		
		if ($(".diggcomment").length!=0) {$(".diggcomment").each(function(i){$(this).html(getHtml($(this).attr("cid"),$(this).attr("digg"),$(this).attr("bury")));});}
		}
		function getRatioByDiggAndBury(d,b) {
			var o = {};
			o.lr = 50;
			if(d+b > 0) o.lr = (b / (d+b))*100;
			o.rr = 100 - o.lr;
			return o;
		}
		// Get the HTML code of each comments
		function getHtml(cid, diggnum, burynum){
			diggnum = parseInt(diggnum);
			burynum = parseInt(burynum);
			var diggandbury = diggnum + burynum;
			var lratio = 50;
			var diggcolor = "#9acd32";
			if(diggandbury > 250){
				diggcolor = "#660066";
			}else if((diggandbury < 250) && (diggandbury >= 200)){
				diggcolor = "#CD3232";
			}else if((diggandbury < 200) && (diggandbury >= 160)){
				diggcolor = "#CD6032";
			}else if((diggandbury < 160) && (diggandbury >= 120)){
				diggcolor = "#CD8832";
			}else if((diggandbury < 120) && (diggandbury >= 80)){
				diggcolor = "#CD9C32";
			}else if((diggandbury < 80) && (diggandbury >= 50)){
				diggcolor = "#cdc432";
			}else if((diggandbury < 50) && (diggandbury >= 20)){
				diggcolor = "#aecd32";
			}else if(diggandbury < 20){
				diggcolor = "#9acd32";
			}
			var burycolor = "#DCDCDC";
			var diggwordcolor = "#000000";
			var burywordcolor = "#000000";
			var barwidth = "100";
			var barheight = "3";
			var o = getRatioByDiggAndBury(diggnum,burynum);
			lratio = o.lr;
			rratio = o.rr;
			var html = '<div class="clearfix">';
			html += '<div class="fR clearfix cd-wrapper" id="cd-wrapper'+cid+'">';
			html += '    <div class="fL">';
			if (checkifcandigg(cid))
			html += '        <a class="cd-votebtn" onclick="diggcomment('+cid+',\'DOWN\','+diggnum+','+burynum+')"><?php echo get_option('cmt_digg_vote_down');?></a> <span id="burynum'+cid+'">'+burynum+'</span>';
			else
			html += '        <span class="cd-votebtn"><?php echo get_option('cmt_digg_vote_down');?></span> <span id="burynum'+cid+'">'+burynum+'</span>';
			html += '   </div>';
			html += '   <div class="fL cd-votebar" id="cd-votebar'+cid+'">';
			html += '  	<table border="0" class="cd-votebar-table">';
			html += '			<tbody>';
			html += '			<tr>';
			html += '			<td style="width:'+lratio+'%;background:#ccc" class="burybar bar" id="burybar'+cid+'" />';
			html += '			<td style="width:'+rratio+'%;background:#ac4" class="diggbar bar" id="diggbar'+cid+'" />';
			html += '		</tr>';
			html += '		</tbody></table>';
			html += '   </div>';
			html += '    <div class="fL">';
			if (checkifcandigg(cid))
			html += '        <span id="diggnum'+cid+'">'+diggnum+'</span> <a class="cd-votebtn" onclick="diggcomment('+cid+',\'UP\','+diggnum+','+burynum+')"><?php echo get_option('cmt_digg_vote_up');?></a>';
			else
			html += '        <span id="diggnum'+cid+'">'+diggnum+'</span> <span class="cd-votebtn"><?php echo get_option('cmt_digg_vote_up');?></span>';
			html += '    </div>';
			html += ' </div>';
			html += '</div>';
			return html;
		}
		function diggcomment(cid, type, diggnum, burynum){
			if(checkifcandigg(cid, type)){
				dodiggit(cid, type, diggnum, burynum)
			}
		}
		function checkifcandigg(cid){
			if($.awc('cmtdiggdigg'+cid) == '1'){
				return false;
			}
			return true;
		}
		function dodiggit(cid, type, diggnum, burynum){
			var url = "<?php echo bloginfo('home');?>";
			updateDiggUI(cid, type, diggnum, burynum);
			$.get(url,{cmtdiggaction:"diggcomment",commentid:cid,diggAction:type,random:Math.random()},function(data){return});
		/* if(data == 'yes'){} Only 'yes' means the digg or bury action is done correctly, we need to make this accurate in the backend as well as the front-end check. No one games the system easily */
		}
		function updateDiggUI(cid, type, diggnum, burynum){
		/* Update the digg/bury bar */
		if(type == 'DOWN') 
			burynum = burynum + 1;
		else
			diggnum = diggnum + 1;
		/* Refresh the display number */
		$("#burynum"+cid).html(String(burynum));
		$("#diggnum"+cid).html(String(diggnum));
		/* Refresh the ratio bar */
		var o = getRatioByDiggAndBury(diggnum,burynum);
		lratio = o.lr;
		rratio = o.rr;
		$("#burybar"+cid).css("width",lratio+"%");
		$("#diggbar"+cid).css("width",rratio+"%");
		$("#cd-wrapper"+cid+" .cd-votebtn").unbind("click").attr("onclick","").css({"color":"#ccc","cursor":"default"});}
	</script>
 	
    <?php
    }
    /**
     * Check whether it is an AJAX request
     *
     * @return boolean Whether it is an AJAX request
     */
    public function isAjax() {
    	return TRUE;
        return ((isset($_SERVER["HTTP_X_REQUESTED_WITH"])) && ($_SERVER["HTTP_X_REQUESTED_WITH"] == 'XMLHttpRequest')) ? TRUE : FALSE;
    }

    /**
     * Gather remote IP
     *
     * @return string
     */
    public function getIp() {
        if ($this->_ip === NULL) {
            if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
                $onlineip = getenv('HTTP_CLIENT_IP');
            } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
                $onlineip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
                $onlineip = getenv('REMOTE_ADDR');
            } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
                $onlineip = $_SERVER['REMOTE_ADDR'];
            }
            preg_match("/[\d\.]{7,15}/", $onlineip, $onlineipmatches);
            $onlineip = isset($onlineipmatches[0]) ? $onlineipmatches[0] : 'unknown';
            $this->_ip = $onlineip;
        }
        return $this->_ip;
    }

    /**
     * Filter, to show the digg/bury button in each comment
     *
     * @param string $cs comment_text
     * @return string comment_text
     */
    public function addItems($cs) {
        global $comment;
        return $cs . '<div digg="' . $comment->digg . '" bury="' . $comment->bury . '" class="diggcomment" cid="' . $comment->comment_ID . '"></div>';
    }

    /**
     * Installation
     */
    public function install() {
        global $wpdb;
        $sql = 'ALTER TABLE `' . $wpdb->comments . '` ADD `digg` MEDIUMINT( 10 ) UNSIGNED NOT NULL DEFAULT \'0\'';
        $wpdb->query($sql);
        $sql = 'ALTER TABLE `' . $wpdb->comments . '` ADD `bury` MEDIUMINT( 10 ) UNSIGNED NOT NULL DEFAULT \'0\'';
        $wpdb->query($sql);
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . 'diggc_log` (
  					`vlid` bigint(20) unsigned NOT NULL auto_increment,
  					`cid` bigint(20) unsigned NOT NULL,
  					`ip` char(15) default NULL,
  					`time` int(10) unsigned NOT NULL,
  					PRIMARY KEY  (`vlid`)
					);';
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        if(!get_option('cmt_digg_vote_up')||get_option('cmt_digg_vote_up')=='') {
        	add_option('cmt_digg_vote_up', 'Yes');
        }
        if(!get_option('cmt_digg_vote_down')||get_option('cmt_digg_vote_down')=='') {
        	add_option('cmt_digg_vote_down', 'No');
        }
    }

    /**
     * Uninstall
     *
     */
    public function uninstall() {
    	/*
        global $wpdb;
        $sql = 'DROP TABLE `' . $wpdb->prefix . 'diggc_log`;';
        $wpdb->query($sql);
        $sql = 'ALTER TABLE `' . $wpdb->comments . '` DROP `bury`;';
        $wpdb->query($sql);
        $sql = 'ALTER TABLE `' . $wpdb->comments . '` DROP `digg`;';
        $wpdb->query($sql);
    	*/
    }

    /**
     * Add admin menus
     *
     */
    public function wpadmin() {
        add_options_page('Comments Digg', 'Comments Digg', 5, __FILE__, array(&$this , 'manage_comment'));
		add_options_page('Comments Digg Options','Comments Digg Options', 'manage_options', dirname (__FILE__).'/cmt-digg-options.php') ;
    }


    /**
     * 后台管理，评论管理
     *
     */
    function manage_comment() {
        if (! is_admin()) {
            return;
        }
        global $wpdb;
        
        // Bubble messages
        if (isset($_REQUEST['cmtdiggaction']) && $_REQUEST['cmtdiggaction'] == 'delete') {
            $ids = isset($_REQUEST['delete_comments']) ? $_REQUEST['delete_comments'] : NULL;
            if (! empty($ids)) {
                if (is_array($ids)) {
                    $ids = '\'' . implode('\',\'', $ids) . '\'';
                } else {
                    $ids = '\'' . $ids . '\'';
                }
                $affected = $wpdb->query("DELETE FROM $wpdb->comments WHERE `comment_ID` IN($ids)");
                echo '<div id="moderated" class="updated fade"><p>'.$affected.' Comment(s) removed!<br /></p></div>';
            }
        }
        /**
         * 分页
         */
        if (isset($_GET['apage'])) {
            $page = abs((int) $_GET['apage']);
        } else {
            $page = 1;
        }
        
        $num = $wpdb->get_var("SELECT COUNT(*) AS num FROM `$wpdb->comments`");//总的数目
        $total = ceil($num / 20);//总页数，每页20条
        $page = $page > $total ? $total : $page;
        $start = ($page - 1) * 20;//开始的位置，每页20条
        
        /**
         * 分页导航
         */
        $page_links = paginate_links(array('base' => add_query_arg('apage', '%#%') , 'format' => '' , 'total' => $total , 'current' => $page));
        $orderby = '';
        /**
         * 排序所依据的字段
         */
        if ($_REQUEST['orderby'] == 'digg') {
            $orderby = 'digg';
        } elseif ($_REQUEST['orderby'] == 'bury') {
            $orderby = 'bury';
        } else {
            $orderby = 'comment_date_gmt';
        }
        /**
         * 升序还是降序排序
         */
        if (isset($_REQUEST['isasc']) && ! $_REQUEST['isasc']) {
            $orderby .= ' ASC';
        } else {
            $orderby .= ' DESC';
        }
        $sql = "SELECT c.*, p.ID, p.post_author, p.post_date, p.post_title FROM {$wpdb->comments} c, {$wpdb->posts} p WHERE c.comment_post_ID = p.ID ORDER BY c.$orderby LIMIT $start, 20";
        $wpdb->query($sql);
        ?>
<div class="wrap">
<h2>Digg Comments Management</h2>

			<script type="text/javascript">
function changestatus(sw){
	var objs = document.getElementsByTagName("input");
	 for(var i=0; i<objs.length; i++) {
		    if(objs[i].type.toLowerCase() == "checkbox" )
		      objs[i].checked = sw.checked;
	}
}
</script>
<form action="<?php echo clean_url(add_query_arg(array('cmtdiggaction' => 'delete'), $_SERVER['REQUEST_URI']))?>" method="post" name="cmtdiggcomment">
<div class="tablenav">
<?php
        if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>";
        ?>
<div class="alignleft">
<button type="submit" onclick=""><?php echo _e('Delete');?></button>
</div>
<br class="clear" />
</div>
<br class="clear" />
<table class="widefat">
	<thead>
		<tr>
    <?php $isasc = (isset($_REQUEST['isasc']) && $_REQUEST['isasc']) ? 0 : 1;?>
    	<th scope="col" class="check-column"><input type="checkbox"
				onclick="changestatus(this)" /></th>
			<th scope="col"><?php _e('Comment')?></th>
			<th scope="col"><a href="<?php echo clean_url(add_query_arg(array('orderby' => 'comment_date_gmt' , 'isasc' => $isasc), $_SERVER['REQUEST_URI']))?>"><?php _e('Date')?></a>
			<?php if (isset($_REQUEST['orderby'])) {
					if ($_REQUEST['orderby'] == 'comment_date_gmt') {
						if (isset($_REQUEST['isasc']) && ! $_REQUEST['isasc']) {
							echo '↑';
						} else {
							echo '↓';
						}
					}
				} ?>
			
			</th>
			<th scope="col"><a href="<?php echo clean_url(add_query_arg(array('orderby' => 'digg' , 'isasc' => $isasc), $_SERVER['REQUEST_URI']))?>">digg</a>
			<?php if (isset($_REQUEST['orderby'])) {
					if ($_REQUEST['orderby'] == 'digg') {
						if (isset($_REQUEST['isasc']) && ! $_REQUEST['isasc']) {
							echo '↑';
						} else {
							echo '↓';
						}
					}
				} ?>
			</th>
			<th scope="col"><a href="<?php echo clean_url(add_query_arg(array('orderby' => 'bury' , 'isasc' => $isasc), $_SERVER['REQUEST_URI']))?>">bury</a>
			<?php if (isset($_REQUEST['orderby'])) {
					if ($_REQUEST['orderby'] == 'bury') {
						if (isset($_REQUEST['isasc']) && ! $_REQUEST['isasc']) {
							echo '↑';
						} else {
							echo '↓';
						}
					}
				} ?>
			
			</th>
			<th scope="col" class="action-links"><?php _e('Delete')?></th>
		</tr>
	</thead>
	<tbody id="the-comment-list" class="list:comment">
<?php
        foreach ($wpdb->last_result as $comment) {
            ?>
<tr>
			<th class="check-column"><input type="checkbox"
				name="delete_comments[]"
				value="<?php echo $comment->comment_ID;?>" /></th>
			<td class="comment">
<?php
            if (! empty($comment->comment_author)) {
                echo "<p class=\"comment-author\"><strong><a class=\"row-title\" href=\"comment.php?action=editcomment&amp;c={$comment->comment_ID}\" title=\"" . __('Edit comment') . "\">{$comment->comment_author}</a></strong><br />";
            }
            if (! empty($comment->comment_author_email)) {
                echo htmlspecialchars($comment->comment_author_email);
            }
            if (! empty($comment->comment_author_IP)) {
                echo ' | ' . $comment->comment_author_IP . '<br>';
            }
            ?>
<?php

            echo '</p><p>' . $comment->comment_content . '</p>';
            echo 'In Entry: <a href="' . site_url() . '?p=' . $comment->comment_post_ID . '#comment-' . $comment->comment_ID . '">' . $comment->post_title . '</a>&nbsp;&nbsp;Post date: ' . $comment->post_date;
            echo "</td>";
            echo "<td>{$comment->comment_date}</td>";
            echo "<td>{$comment->digg}</td>";
            echo "<td>{$comment->bury}</td>";
            echo "<td class='action-links'><a href=\"" . clean_url(add_query_arg(array('cmtdiggaction' => 'delete' , 'delete_comments' => $comment->comment_ID), $_SERVER['REQUEST_URI'])) . "\">";
            echo _e('Delete');
            echo "</a></td></tr>";
        }
        ?>

	
	
	
	</tbody>
</table>
<div class="tablenav">
<?php
        if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>";
        ?>
<div class="alignleft">
<button type="submit" onclick=""><?php echo _e('Delete');?></button>
</div>
<br class="clear" />

</div>

<br class="clear" />
</form>
</div>
<?php
    }
}
$cmtdiggdigg = new cmtdigg_digg();
register_activation_hook(__FILE__, array(&$cmtdiggdigg , 'install')); // Installation
register_deactivation_hook(__FILE__, array(&$cmtdiggdigg , 'uninstall')); // Uninstallation
add_filter('comment_text', array(&$cmtdiggdigg , 'addItems'), 99999); // Filter, add the vote data to each comment thread
add_action('wp_head', array(&$cmtdiggdigg , 'head_include')); // JavaScript injection for digg-comments implementation
$cmtdiggdigg->ajax_digg(); // Accept AJAX request
add_action('admin_menu', array(&$cmtdiggdigg , 'wpadmin')); // Admin menu

/**
 * 当$type为数字时，表示评论id，这时获取的是关于某偏文章的的评论的digg降序输出
 * 当$type为daily、weekly、monthly、yearly时，获取的是对应日、周、月、年之内的评论的digg降序输出
 * 当$start和$end同时为真时，将获的是$start和$end所表示的时间之间的评论的digg降序输出，这时第一个参数 $type无效评论的
 * $start 和$endde 格式为2009-01-06 09:20:04
 * $num 输出的条数
 * 
 *
 * @param string | numberic $type
 * @param int $num
 * @param time $start
 * @param time $end
 * @return string
 */
function get_most_digg_comments_by_range($type = "daily", $num = 10, $start = false, $end = false) {
    global $wpdb;
    if ($start && $end) {
        $sql = "SELECT * FROM `$wpdb->comments` WHERE (comment_date > '$start' and comment_date < '$end') ORDER BY digg DESC  LIMIT 0, " . $num;
    } else {
        if (is_numeric($type)) {
            $sql = 'SELECT * FROM `' . $wpdb->comments . '` WHERE comment_post_ID = ' . $type . ' ORDER BY digg DESC  LIMIT 0, ' . $num;
        } else {
            $datetime = '';
            switch ($type) {
                case 'daily':
                    //date('Y-m-d h:i:s')
                    $datetime = date('Y-m-d');
                    break;
                case 'weekly':
                    $start = date('w');
                    $start = ($start == 0) ? 7 : $start;
                    $start = date('d') - $start;
                    $datetime = date('Y-m-') . $start . ' 00:00:00';
                    break;
                case 'monthly':
                    $datetime = date('Y-m-') . '00 00:00:00';
                    break;
                case 'yearly':
                    $datetime = date('Y-') . '00-00 00:00:00';
                    break;
                default:
                    return;
                    break;
            }
            $sql = "SELECT * FROM `$wpdb->comments` WHERE comment_date > '$datetime' ORDER BY digg DESC LIMIT 0, $num";
        }
    }
    $wpdb->query($sql);
    $result = '';
    foreach ($wpdb->last_result as $row) {
        $result .= '<li><a href="'.site_url().'?p=' . $row->comment_post_ID . '#comment-' . $row->comment_ID . '">' . $row->comment_author . ':' . $row->comment_content . '</a></li>';
        //$result .= '<li><a href="'.site_url().'?p='. $row->comment_post_ID . '#comment-' . $row->comment_ID .'">{' . $row->comment_author . '}:{' . $row->comment_content . '}</a></li>';
    }
    return $result;
}
?>