<?php
/**
 * MyBB 1.0
 * Copyright � 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/*
Example code:

$syndication = new Syndication();
$syndication->set_feed_type('atom');
$syndication->set_limit(20);
$forums = array(1, 5, 6);
$syndication->set_forum_list($forums);
$syndication->generate_feed();

*/

class Syndication
{
	/**
	 * The type of feed to generate.
	 *
	 * @var string
	 */
	var $feed_type = 'rss0.92';

	/**
	 * The number of items to list.
	 *
	 * @var int
	 */
	var $limit = 15;

	/**
	 * The list of forums to grab from.
	 *
	 * @var array
	 */
	var $forumlist;

	/**
	 * Set the type of feed to be used.
	 *
	 * @param string The feed type.
	 */
	function set_feed_type($feed_type)
	{
		if($feed_type == 'rss2.0')
		{
			$this->feed_type = 'rss2.0';
		}
		elseif($feed_type == 'atom1.0')
		{
			$this->feed_type = 'atom1.0';
		}
		else
		{
			$this->feed_type = 'rss0.92';
		}
	}

	/**
	 * Set the number of posts to generate in the feed.
	 *
	 * @param int The number of posts.
	 */
	function set_limit($limit)
	{
		if($limit < 1)
		{
			error($lang->error_invalid_limit);
		}
		else
		{
			$this->limit = intval($limit);
		}
	}

	/**
	 * Set the forum(s) to grab posts from.
	 *
	 * @param array Array of forum ids.
	 */
	function set_forum_list($forumlist = array())
	{
		$unviewable = getunviewableforums();
		if($unviewable)
		{
			$unviewable = "AND f.fid NOT IN($unviewable)";
		}

		if(!empty($forumlist))
		{
			$forum_ids = "'-1'";
			foreach($forumlist as $fid)
			{
				$forum_ids .= ",'".intval($fid)."'";
			}
			$this->forumlist = "AND f.fid IN ($forum_ids) $unviewable";
		}
		else
		{
			$this->forumlist = $unviewable;
		}
	}

	/**
	 * Generate and echo XML for the feed.
	 *
	 */
	function generate_feed()
	{
		// Output an appropriate header matching the feed type.
		switch($this->feed_type)
		{
			case "atom1.0":
				header("Content-Type: application/atom+xml");
			break;

			case "rss0.92":
			case "rss2.0":
			default:
				header("Content-Type: application/rss+xml");
			break;
		}

		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";

		// Build the parts of the feed.
		$this->build_header();
		$this->build_entries();
		$this->build_footer();
	}

	/**
	 * Build the feed header.
	 *
	 */
	function build_header()
	{
		global $mybb, $lang, $mybboard;
		switch($this->feed_type)
		{
			case "rss2.0":
				echo "<rss version=\"2.0\">\n";
				echo "\t<channel>\n";
				echo "\t\t<title>".htmlentities($mybb->settings['bbname'])."</title>\n";
				echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
				echo "\t\t<description>".htmlentities($mybb->settings['bbname'])." - ".$mybb->settings['bburl']."</description>\n";
				echo "\t\t<lastBuildDate>".date("D, d M Y H:i:s O")."</lastBuildDate>\n";
				echo "\t\t<generator>MyBB ".$mybboard['internalver']."</generator>\n";
			break;

			case "atom1.0":
				echo "<feed xmlns=\"http://www.w3.org/2005/Atom\">\n";
				echo "\t<title>".htmlentities($mybb->settings['bbname'])."</title>\n";
				echo "\t<id>".$mybb->settings['bburl']."/</id>\n";
				echo "\t<link rel=\"self\" href=\"".$mybb->settings['bburl']."/syndication.php?type=atom1.0&amp;limit=".$this->limit."\"/>\n";
				echo "\t<updated>".date("Y-m-d\TH:i:s\Z")."</updated>\n";
				echo "\t<generator uri=\"http://mybboard.com\" version=\"".$mybboard['internalver']."\">MyBB</generator>\n";
			break;

			case "rss0.92":
				echo "<rss version=\"0.92\">\n";
				echo "\t<channel>\n";
				echo "\t\t<title>".htmlentities($mybb->settings['bbname'])."</title>\n";
				echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
				echo "\t\t<description>".htmlentities($mybb->settings['bbname'])." - ".$mybb->settings['bburl']."</description>\n";
				echo "\t\t<lastBuildDate>".date("D, d M Y H:i:s O")."</lastBuildDate>\n";
				echo "\t\t<language>en</language>\n";
			break;

			default:
				echo "<rss version=\"0.92\">\n";
				echo "\t<channel>\n";
				echo "\t\t<title>".htmlentities($mybb->settings['bbname'])."</title>\n";
				echo "\t\t<link>".$mybb->settings['bburl']."</link>\n";
				echo "\t\t<description>".htmlentities($mybb->settings['bbname'])." - ".$mybb->settings['bburl']."</description>\n";
				echo "\t\t<lastBuildDate>".date("D, d M Y H:i:s O")."</lastBuildDate>\n";
				echo "\t\t<language>en</language>\n";
			break;
		}
	}

	/**
	 * Build the feed entries.
	 *
	 */
	function build_entries()
	{
		global $db, $mybb, $lang;
		$query = $db->query("
			SELECT t.*, f.name AS forumname, p.message AS postmessage
			FROM ".TABLE_PREFIX."threads t
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid)
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=t.firstpost)
			WHERE 1=1
			AND p.visible=1 $this->forumlist
			ORDER BY t.dateline DESC
			LIMIT 0, ".$this->limit
		);
		while($thread = $db->fetch_array($query))
		{
			$thread['subject'] = htmlentities($thread['subject']);
			$thread['forumname'] = htmlentities($thread['forumname']);
			$postdate = mydate($mybb->settings['dateformat'], $thread['dateline'], "", 0);
			$posttime = mydate($mybb->settings['timeformat'], $thread['dateline'], "", 0);
			$thread['postmessage'] = nl2br(htmlspecialchars_uni($thread['postmessage']));
			$last_updated = mydate("D, d M Y H:i:s O", $thread['dateline'], "", 0);
			$last_updated_atom = mydate("Y-m-d\TH:i:s\Z", $thread['dateline'], "", 0);
			switch($this->feed_type)
			{
				case "rss2.0";
					echo "\t\t<item>\n";
					echo "\t\t\t<guid>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</guid>\n";
					echo "\t\t\t<title>".$thread['subject']."</title>\n";
					echo "\t\t\t<author>".$thread['username']."</author>\n";
					$description = htmlentities($lang->forum." ".$thread['forumname'])."\r\n<br />".htmlentities($lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
					if($thread['postmessage'])
					{
						$description .= "\n<br />".htmlentities($thread['postmessage']);
					}
					echo "\t\t\t<description><![CDATA[".$description."]]></description>";
					echo "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</link>\n";
					echo "\t\t\t<category domain=\"".$mybb->settings['bburl']."/forumdisplay.php?fid=".$thread['fid']."\">".$thread['forumname']."</category>\n";
					echo "\t\t\t<pubDate>".$last_updated."</pubDate>\n";
					echo "\t\t</item>\n";
				break;

				case "atom1.0":
					echo "\t<entry>\n";
					echo "\t\t<id>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</id>\n";
					echo "\t\t<title>".$thread['subject']."</title>\n";
					echo "\t\t<updated>".$last_updated_atom."</updated>\n";
					echo "\t\t<author>\n";
					echo "\t\t\t<name>".$thread['username']."</name>\n";
					echo "\t\t</author>\n";
					$description = htmlentities($lang->forum." ".$thread['forumname'])."\r\n<br />".htmlentities($lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
					if($thread['postmessage'])
					{
						$description .= "\n<br />".htmlentities($thread['postmessage']);
					}
					echo "\t\t\t<content type=\"html\"><![CDATA[".$description."]]></content>";
					echo "\t</entry>\n";
				break;

				case "rss0.92":
					echo "\t\t<item>\n";
					echo "\t\t\t<title>".$thread['subject']."</title>\n";
					echo "\t\t\t<author>".$thread['username']."</author>\n";
					$description = htmlentities($lang->forum." ".$thread['forumname'])."\r\n<br />".htmlentities($lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
					if($thread['postmessage'])
					{
						$description .= "\n<br />".htmlentities($thread['postmessage']);
					}
					echo "\t\t\t<description><![CDATA[".$description."]]></description>";
					echo "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</link>\n";
					echo "\t\t</item>\n";
				break;

				default:
					echo "\t\t<item>\n";
					echo "\t\t\t<title>".$thread['subject']."</title>\n";
					echo "\t\t\t<author>".$thread['username']."</author>\n";
					$description = htmlentities($lang->forum." ".$thread['forumname'])."\r\n<br />".htmlentities($lang->posted_by." ".$thread['username']." ".$lang->on." ".$postdate." ".$posttime);
					if($thread['postmessage'])
					{
						$description .= "\n<br />".htmlentities($thread['postmessage']);
					}
					echo "\t\t\t<description><![CDATA[".$description."]]></description>";
					echo "\t\t\t<link>".$mybb->settings['bburl']."/showthread.php?tid=".$thread['tid']."&amp;action=newpost</link>\n";
					echo "\t\t</item>\n";
				break;
			}
		}
	}

	/**
	 * Build the feed footer.
	 *
	 */
	function build_footer()
	{
		switch($this->feed_type)
		{
			case "rss2.0":
				echo "\t</channel>\n";
				echo "</rss>";
			break;

			case "atom1.0":
				echo "</feed>";
			break;

			case "rss0.92":
				echo "\t</channel>\n";
				echo "</rss>";
			break;

			default:
				echo "\t</channel>\n";
				echo "</rss>";
			break;
		}
	}

}
?>