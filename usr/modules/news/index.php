<?php

/**
* @name        JMY CMS
* @link        http://jmy.su/
* @copyright   Copyright (C) 2012-2014 JMY LTD
* @license     LICENSE.txt (see attached file)
* @version     VERSION.txt (see attached file)
* @author      Komarov Ivan
*/

//редакция 19.01.2015
 
if (!defined('ACCESS')) {
    header('Location: /');
    exit;
}

loadConfig('news');
require_once ROOT . 'usr/modules/news/feed/index.php';

function main($cat = null) 
{
global $db, $config, $core, $tags, $news_conf, $url, $headTag;
	
	if(!$cat) 
	{
		set_title(array(_NEWS));
		$where = ' AND n.allow_index != \'0\'';
		$link = '';
		$core->tpl->uniqTag = 'main';
	} 
	elseif($cat == 'tag')
	{
		$blockCL = true;
		set_title(array(_NEWS, _TAG, $url[2]));
		$core->tpl->title(_TAG . ': '.$url[2]);
		$where = 'AND tags regexp \'[[:<:]](' . $db->safesql($url[2]) . ')[[:>:]]\'';
		$link = '/tags/' . $url[2];
		
		$core->tpl->uniqTag = 'cat';
	}
	elseif($cat == 'date')
	{
		$blockCL = true;
		$date_new = $url[2];
		$date_arr = explode('-', $date_new);
		set_title(array(_NEWS, $date_new));
		$core->tpl->title(_DATE . ': ' . $date_new);

		if(isset($date_arr[2]))
		{
			$where = "AND `date` BETWEEN " . mktime(0, 0, 0, $date_arr[1], $date_arr[2], $date_arr[0]) . " AND " . mktime(0, 0, 0, $date_arr[1], $date_arr[2]+1, $date_arr[0]);
		}
		else
		{
			$where = "AND `date` BETWEEN " . mktime(0, 0, 0, $date_arr[1], 1, $date_arr[0]) . " AND " . mktime(0, 0, 0, $date_arr[1]+1, 1, $date_arr[0]);
		}
		
		$link = '/date/' . $date_new;
		
		$core->tpl->uniqTag = 'main';
	}
	else
	{
		$cat = mjsEnd($url);
		

		$altname = filter($cat, 'a');
		$cat_query = $db->query("SELECT id as cid, name, description, keywords FROM ".DB_PREFIX."_categories WHERE altname='" . $db->safesql($altname) . "'");
		
		if($db->numRows($cat_query) == 0)
		{
			location();
		}
		
		$cat_info = $db->getRow($cat_query);
		
		if(!empty($cat_info['keywords']))
		{
			$core->tpl->keywords = $cat_info['keywords'];
		}
		if(!empty($cat_info['description']))
		{
			$core->tpl->description = $cat_info['description'];
		}
		
		$core->tpl->uniqTag = 'cat';
		$core->tpl->feed_link = 'cat/' . $cat_info['cid'];
		
		set_title(array($cat_info['name']));
		$where = "AND cat like '%," . $cat_info['cid'] . ",%'";
		$cat_ids = getcache('categories');
		$cat_pod = false;
		foreach($cat_ids as $cid => $val) 
		{
			foreach($val as $pid => $name) 
			{
				if($pid == $cat_info['cid']) $cat_pod .= ','.$cid;
			}
		}
		
		
		$link = '/' . $core->getCat('news', $cat_info['cid'], 'development');
	}

	$where .= ' AND n.date <= \'' . time() . '\'';
	
	$page = init_page();
	$cut = ($page-1)*$news_conf['num'];
	$query = $db->query("SELECT n.*, c.* FROM ".DB_PREFIX."_news as n LEFT JOIN ".DB_PREFIX."_langs as c on(c.postId=n.id and c.module='news') WHERE active='1' " . $where . " ORDER BY fixed DESC, date DESC LIMIT " . $cut . ", " . $news_conf['num'] . "");
	
	if($db->numRows($query) > 0) 
	{
	
		while($news = $db->getRow($query)) 
		{
			$tag_list = explode(', ', $news['tags']);
			$tag_count = 0;
			$tags = false;
			
			foreach($tag_list as $tag) 
			{
				$tag_count++;
				if($tag_count < ($news_conf['tags_num']+1)) 
				{
					$tags .= '<a href="news/tags/' . $tag . '" title="' . $tag . '">' . ($headTag == $tag ? '<strong>' . $tag . '</strong>' : $tag) . '</a>, ';
				}
			}
			$catInfo = $news['cat'] !== ',0,' ? $core->catInfo('news', $news['cat']) : '';
			$cat = $news['cat'] !== ',0,' ? $core->getCat('news', $news['cat'], 'short', 3) : '';
			$news_link = $news['cat'] !== ',0,' ? 'news/' . $core->getCat('news', $news['cat'], 'development') . '/' : 'news/';
			$cat_one = $news['cat'] !== ',0,' ? $core->getCat('news', $news['cat'], 'altname', 1) : 'index';
			$short = $core->bbDecode($news['short'], $news['id'], true);
			$core->tpl->loadFile('news/news-'.(is_array($core->tpl->uniqTag) ? $core->tpl->uniqTag[0] : $core->tpl->uniqTag));
			$core->tpl->setVar('TITLE', $news['title']);
			$core->tpl->setVar('SHORT', $short);
			$core->tpl->setVar('FULL', '<div id="full-' . $news['id'] . '">' . $core->bbDecode($news['full'], $news['id'], true) . '</div>');
			$core->tpl->setVar('CATEGORY', $cat);
			$core->tpl->setVar('CAT_ONE', $cat_one);
			$core->tpl->setVar('ALTNAME', $news['altname']);
			$core->tpl->setVar('ICON', isset($catInfo['icon']) ? $core->getCatImg($news_link, $catInfo['icon'], $catInfo['title']) : '');
			$core->tpl->setVar('AUTHOR', '<a href="profile/' . $news['author'] . '" title="' . _PAGE . ': ' . $news['author'] . '">' . $news['author'] . '</a>');
			$core->tpl->setVar('VIEWS', $news['views']);
			$core->tpl->setVar('COMMENTS', $news['comments']);
			$core->tpl->setVar('TAGS', mb_substr($tags, 0, -2));
			$core->tpl->setVar('FULL_LINK', $news_link . $news['altname'] . ".html");
			$miniImg = _getCustomImg($short);
			$array_replace = array(
				"#\\[tags\\](.*?)\\[/tags\\]#ies" => "if_set('" . $news['tags'] . "', '\\1')",
				"#\\[more\\](.*?)\\[/more\\]#ies" => "format_link('\\1', '" . $news_link . $news['altname'] . ".html')",
				"#\\[category\\](.*?)\\[/category\\]#ies" => "if_set('".$cat."', '\\1')",
				"#\\{%MYDATE:(.*?)%\\}#ies" => "date('\\1', '" . $news['date'] . "')",
				"#\\{%TITLE:(.*?)%\\}#ies" => "short('\\1', '" . $news['title'] . "')",
				"#\\{%SHORT:(.*?)%\\}#ies" => "short('\\1', '" . $short . "')",
				"#\\[img:([0-9]*?)\\]#is" => (!empty($miniImg[0]) ? '<img src="' . $miniImg[0] . '" border="0" width="\\1" />' : ''),				
				"#\\[mini_img\\](.*?)\\[/mini_img\\]#ies" => "if_set('" . (!empty($miniImg[0]) ? true : '') . "', '\\1')",
			);
			if(!empty($news['fields']))
			{
				$fields = unserialize($news['fields']);
				foreach($fields as $xId => $xData)
				{
					if(!empty($xData[1]))
					{
						$array_replace["#\\[xfield_value:" . $xId . "\\]#is"] = $xData[1];
					}
				}
			}
			$array_replace["#\\[xfield:([0-9]*?)\\](.*?)\\[/xfield:([0-9]*?)\\]#ies"] = "ifFields('" . $news['fields'] . "', '\\1', '\\2')";
			$core->tpl->sources = preg_replace(array_keys($array_replace), array_values($array_replace), $core->tpl->sources);
			$core->tpl->setVar('DATE', formatDate($news['date']));
			$core->tpl->setVar('ID', $news['id']);
			$core->tpl->setVar('RATING', $news['allow_rating'] ? draw_rating($news['id'], 'news', $news['score'], $news['votes']) : '');
			$core->tpl->setVar('EDIT', $core->auth->isAdmin ? "<a onclick=\"return dropdownmenu(this, event, menu_news, '150px', '" . $news['id'] . "', 'short')\" onmouseout=\"delayhidemenu()\" href=\"javascript:void(0);\"><img src=\"media/edit/plus.png\" border=\"0\" class=\"icon\" alt=\"\" /></a>" : '');
			$core->tpl->end();
			unset($tags);
		}
		
		list($all) = $db->fetchRow($db->query("SELECT count(n.id) FROM ".DB_PREFIX."_news as n LEFT JOIN ".DB_PREFIX."_langs as c on(c.postId=n.id and c.module='news') WHERE active='1' " . $where));
		$core->tpl->pages($page, $news_conf['num'], $all, 'news' . $link . '/{page}');
	
			
	} 
	else 
	{
		$core->tpl->info(_NEWSNOTFOUND);
	}
}


function view($tran = false)
{
global $db, $config, $core, $tags, $news_conf, $url, $headTag, $cache;

		$where = 'altname';
		if($tran)
		{
			$translate = str_replace(array('.html', '.htm'), array('', ''), empty($tran) ? $url[2] : filter($tran, 'a'));
		}
		else
		{
			$translate = str_replace(array('.html', '.htm'), array('', ''), $url[2]);
		}
		
		if(is_numeric(empty($tran) ? $url[2] : $tran)) $where = 'id';
				
		$query = $db->query("SELECT n.*, l.*, c.id as cid, c.name, c.altname as alturl, c.icon as catIcon FROM ".DB_PREFIX."_news AS n LEFT JOIN ".DB_PREFIX."_categories AS c ON (n.cat=c.id) LEFT JOIN ".DB_PREFIX."_langs as l on(l.postId=n.id and l.module='news') WHERE n." . $where . "='" . $db->safesql($translate) . "' AND l.lang = '" . $core->InitLang() . "'");
		if($db->numRows($query) == 0)
		{
			location();
		}
		
		$news = $db->getRow($query);
		$textFull = $news['full'];
		$pageContent = init_page('break');
		$pageBreaks = explode('[pagebreak]', $textFull);
		if(!isset($pageBreaks[$pageContent-1])) $pageContent = 1;		
		$textFull = $pageBreaks[$pageContent-1];
		$textShort = $news['short'];
		$db->query("UPDATE `" . DB_PREFIX . "_news` SET views = views+1 WHERE " . $where . "='" . $db->safesql($translate) . "' LIMIT 1 ;");
		$tag_list = explode(',', $news['tags']);
		$tag_count = 0;
		$tags = false;
		
		if(!empty($news['keywords']))
		{
			$core->tpl->keywords =$news['keywords'];
		}
		if(!empty($news['description']))
		{
			$core->tpl->description = $news['description'];
		}
		$core->tpl->adminBar = '<a href="' . ADMIN . '/module/news/edit/' . $news['id'] . '"><img src="media/edit/edit.png" border="0" class="icon" style="padding-right:3px; ">' . _EDIT_NEWS . '</a> <a onсlick="return getConfirm(\'Удалить новость - ' . $news['title'] . '?\')"  href="' . ADMIN . '/module/news/delete/' . $news['id'] . '"><img src="media/edit/cross.png" border="0" class="icon" style="padding-left:5px; ">' . _DELETE_NEWS . '</a>';

		foreach($tag_list as $tag) 
		{
			$tag_count++;
			$tag = trim($tag);
			if($tag_count < ($news_conf['tags_num']+1)) 
			{
				$tags .= '<a href="news/tags/'.urlencode($tag).'" title="' . $tag . '">'.$tag.'</a>, ';
			}
		}
		
		if($news_conf['tagIll'])
		{
			$tagFormat = '';
			
			foreach($tag_list as $ttag)
			{
				$ttag = trim($ttag);
				$tagFormat .= "'" . $ttag  . "' => '" . str_replace('{tag}', $ttag, $news_conf['illFormat'])  . "', ";
			}
			eval('$tagFormated = array(' . $tagFormat . ');');
			$textFull = str_ireplace(array_keys($tagFormated), array_values($tagFormated), $textFull);
			$textShort = str_ireplace(array_keys($tagFormated), array_values($tagFormated), $textShort);
		
		}
		
		$cat_id = str_replace(',', '', $news['cat']);
		$query = $db->query("SELECT * FROM ".DB_PREFIX."_categories WHERE id = '" . $cat_id . "'");	
		$cat_for = $db->getRow($query);
		
			
		if($pagesBr > 1 && $pageBreaks[1] !== '')
		{
			if(isset($pageBreaks[$pageContent-2]))
			{
				$breakNav .= "<a href=\"" . $news_link . 'break/'. ($pageContent-1) . '/' . $news['altname'] . ".html\" title=\"Назад\">" . _PREVIOUS_PAGE . "</a>";
			}			
			if(isset($pageBreaks[$pageContent-2]) && isset($pageBreaks[$pageContent])) $breakNav .= ' | ';			
			if(isset($pageBreaks[$pageContent]))
			{
				$breakNav .= "<a href=\"" . $news_link . 'break/'. ($pageContent+1) . '/' . $news['altname'] . ".html\" title=\"" . _NEXT_PAGE . "\">" . _NEXT_PAGE . "</a>";
			}
		}		
			
		
		$core->tpl->uniqTag = 'view';
		set_title(array(_NEWS, $news['name'], $news['title']));
		$short = $core->bbDecode($textShort, $news['id'], true);
		$miniImg = _getCustomImg($short);
		$core->tpl->loadFile('news/news-' . (is_array($core->tpl->uniqTag) ? $core->tpl->uniqTag[0] : $core->tpl->uniqTag));
		$core->tpl->setVar('TITLE', $news['title']);
		$core->tpl->setVar('SHORT', '<div id="short-' . $news['id'] . '">' . $short . '</div>');
		$core->tpl->setVar('FULL', !empty($news['full']) ? '<div id="full-' . $news['id'] . '">' . $core->bbDecode($textFull, $news['id'], true) . '</div>' : '<div id="short-' . $news['id'] . '">' . $core->bbDecode($textShort, $news['id'], true) . '</div>');
		$core->tpl->setVar('CATEGORY', $cat);
		$core->tpl->setVar('ALTNAME', $news['altname']);
		$core->tpl->setVar('AUTHOR', '<a href="profile/' . $news['author'] . '" title="' . _PAGE . ': ' . $news['author'] . '">' . $news['author'] . '</a>');
		$core->tpl->setVar('VIEWS', $news['views']);
		$core->tpl->setVar('COMMENTS', $news['comments']);
		$core->tpl->setVar('ICON', isset($news['catIcon']) ? $core->getCatImg($news_link, $news['catIcon'], $news['name']) : '');
		$core->tpl->setVar('CAT_ONE', $cat_one);
		$core->tpl->setVar('BREAKNAV', $breakNav);
		$core->tpl->setVar('FULL_LINK', $news_link . $news['altname'] . ".html");
		$core->tpl->setVar('TAGS', mb_substr($tags, 0, -2));
		$array_replace = array(
			"#\\[tags\\](.*?)\\[/tags\\]#ies" => "if_set('" . $news['tags'] . "', '\\1')",
			"#\\[more\\](.*?)\\[/more\\]#ies" => "format_link('\\1', '" . $news_link . $news['altname'] . ".html')",
			"#\\[category\\](.*?)\\[/category\\]#ies" => "if_set('".$cat."', '\\1')",
			"#\\{%MYDATE:(.*?)%\\}#ies" => "date('\\1', '" . $news['date'] . "')",
			"#\\{%TITLE:(.*?)%\\}#ies" => "short('\\1', '" . $news['title'] . "')",
			"#\\{%SHORT:(.*?)%\\}#ies" => "short('\\1', '" . $short . "')",
			"#\\[img:([0-9]*?)\\]#is" => (!empty($miniImg[0]) ? '<img src="' . $miniImg[0] . '" border="0" width="\\1" />' : ''),
			"#\\[mini_img\\](.*?)\\[/mini_img\\]#ies" => "if_set('" . (!empty($miniImg[0]) ? true : '') . "', '\\1')",
			"#\\[xfield:([0-9]*?)\\](.*?)\\[/xfield:([0-9]*?)\\]#ies" => "ifFields('" . $news['fields'] . "', '\\1', '\\2')",
		);
		if(!empty($news['fields']))
		{
			$fields = unserialize($news['fields']);
			foreach($fields as $xId => $xData)
			{
				if(!empty($xData[1]))
				{
					$array_replace["#\\[xfield_value:" . $xId . "\\]#is"] = $xData[1];
				}
			}
		}
		if($news_conf['showBreadcumb'] == '1')
		{
			$catId = explode(',', $news['cat']);
			$core->tpl->setVar('BREADCUMB', $core->getCat('news', ($catId[1] != 0) ? $catId[1] : '', 'breadcrumb', 1));
		}
		$core->tpl->setVar('DATE', formatDate($news['date']));
		$core->tpl->setVar('ID', $news['id']);
		$core->tpl->setVar('RATING', $news['allow_rating'] ? draw_rating($news['id'], 'news', $news['score'], $news['votes']) : '');
		$core->tpl->setVar('EDIT', $core->auth->isAdmin ? "<a onclick=\"return dropdownmenu(this, event, menu_news, '150px', '" . $news['id'] . "', '" . (!empty($news['full']) ? 'full' : 'short') . "')\" onmouseout=\"delayhidemenu()\" href=\"javascript:void(0);\"><img src=\"media/edit/plus.png\" border=\"0\" class=\"icon\" alt=\"\" /></a>" : '');
		$related_cache = $cache->do_get('related_'.$news['id']);
		if(empty($related_cache) && $news_conf['related_news'] > 0)
		{
			$body_text = $news['title'] . strip_tags(stripslashes(" " . (!empty($news['full']) ? $news['full'] : $news['short'])));
			if(!empty($body_text))
			{
				$rel_query = $db->query("SELECT n.*, l.* FROM ".DB_PREFIX."_news AS n LEFT JOIN ".DB_PREFIX."_langs as l on(l.postId=n.id and l.module='news') WHERE MATCH (`title`, `short`, `full`) AGAINST ('+(" . $db->safesql($body_text) . ")' IN BOOLEAN MODE) AND n.id != " . $news['id'] . " LIMIT ".$news_conf['related_news'], true);
				$related_cache = '';
				if($db->numRows($rel_query) > 0)
				{
					while($related = $db->getRow($rel_query)) 
					{
						$rel_link = $related['cat'] !== ',0,' ? 'news/' . $core->getCat('news', $related['cat'], 'development') . '/' : 'news/';
						$related_cache .= '<li><a href="'.$rel_link . $related['altname'] . '.html">'.$related['title'].' (' . formatdate($related['date']) . ')</a></li>';
					}
				}
				
				$cache->do_put('related_'.$news['id'], $related_cache, 3600);
			}
		}
		$core->tpl->setVar('RELATED', $related_cache);
		$array_replace["#\\[related\\](.*?)\\[/related\\]#is"] = (!empty($related_cache) ? '\\1' : '');
		$core->tpl->sources = preg_replace(array_keys($array_replace), array_values($array_replace), $core->tpl->sources);
		$core->tpl->end();
		
		if($news['allow_comments']) 
		{
			show_comments('news', $news['id'], $news_conf['comments_num']);
		}
        else
        {
            $core->tpl->info(_NEWS_COMMENTS_OFF);   
        }

		
}

switch(isset($url[1]) ? $url[1] : null) 
{
	default:
	
		if(eregStrt('.htm', mjsEnd($url)))
		{
			view(mjsEnd($url));
		}
		else
		{
			main(isset($url[1]) && $url[1] != 'page' ? $url[1] : false);
		}
	break;
	
	case 'addPost':
		set_title(array(_NEWS, _ADD_NEWS));		
		if($core->auth->isUser && $core->auth->group_info['addPost'] == 1)
		{
			$query = $db->query("SELECT * FROM `".DB_PREFIX."_news` WHERE `active`=2");
			if($db->numRows($query) > $news_conf['preModer']) 
			{
				$core->tpl->info("Администратором сайта был установлен лимит на максимальное количество новостей, допустимых к ожиданию на модерацию. На данный момент на сайт добавлено максимальное количество новостей, которые ожидают проверки администратором. Попробуйте добавить вашу новость немного позднее.");
			}
			else
			{
				$core->tpl->info("Все новости перед публикацией проходят процесс модерации администратором. Поэтому будте внимательны при заполнении всех полей формы, старайтесь не допускать ошибок.");
				$core->tpl->open();
				$cats_arr = $core->aCatList('news');
				$cats = '';
				foreach ($cats_arr as $cid => $name) 
				{
					$cats .= '<option value="' . $cid . '">' . $name . '</option>';
				}
				if($core->auth->user_info['loadAttach'] && $news_conf['fileEditor'] == 1)
				{
					require ROOT . 'usr/plugins/ajax_upload/init.php';
				}
				$queryF = $db->query("SELECT * FROM ".DB_PREFIX."_xfields WHERE module='news' and to_user='1'");
				$xfileds = '';
				if($db->numRows($queryF) > 0) 
				{
					while($xfield = $db->getRow($queryF)) 
					{
						if($xfield['type'] == 3)
						{
							$dxfield = array_map('trim', explode("\n", $xfield['content']));
							$xfieldChange = '<select class="textinput" name="xfield[' . $xfield['id'] . ']">';

							foreach($dxfield as $xfiled_content)
							{
								$xfieldChange .= '<option value="' . $xfiled_content . '" ' . (isset($fields[$xfield['id']][1]) && $fields[$xfield['id']][1] == $xfiled_content ? 'selected' : ''). '>' . $xfiled_content . '</option>';
							}
							$xfieldChange .= '</select>';
						}
						elseif($xfield['type'] == 2)
						{
							$xfieldChange = '<textarea class="textarea" name="xfield[' . $xfield['id'] . ']" >' . (!empty($fields[$xfield['id']][1]) ? $fields[$xfield['id']][1] : $xfield['content']) . '</textarea>';
						}
						else
						{
							$xfieldChange = '<input type="text" class="textinput" name="xfield[' . $xfield['id'] . ']" value="' . (!empty($fields[$xfield['id']][1]) ? $fields[$xfield['id']][1] : $xfield['content']) . '" />';
						}
						
						$xfileds .= '<div class="padding inputTitle"><input type="hidden" name="xfieldT[' . $xfield['id'] . ']" value="' . $xfield['title'] . '" />' . $xfield['title'] . ':</div><div class="padding" style="padding-bottom:10px;">' . $xfieldChange . '</div>';
					}
				}
				$bbShort = bb_area('short', '', 5, 'textarea', '', true);
				$bbFull = '<textarea cols="30" rows="5" name="full" class="textarea" id="full" onclick="mainArea(\'full\')"></textarea>';
				$core->tpl->loadFile('news/news-add');
				$core->tpl->setVar('BB_SHORT', $bbShort);
				$core->tpl->setVar('BB_FULL', $bbFull);
				$core->tpl->setVar('XFILEDS', $xfileds);
				$core->tpl->setVar('FILE_UPLOAD', ($core->auth->user_info['loadAttach'] && $news_conf['fileEditor'] == 1 ? file_upload('user', false, 'news') : ''));
				$core->tpl->sources = preg_replace("#\\[fileupload\\](.*?)\\[/fileupload\\]#is", ($core->auth->user_info['loadAttach'] && $news_conf['fileEditor'] == 1 ? '\\1' : ''), $core->tpl->sources);	
				$core->tpl->setVar('CATS', $cats);
				$core->tpl->end();
				$core->tpl->close();
			}
		}
		else
		{
			$core->tpl->info("Чтобы размещать новости необходимо пройти процесс <a href=\"profile/registration\" title=\"Регистрация\">регистрации</a> на сайте!");
		}
	
		break;
		
	case 'savePost':
		if($core->auth->isUser && $core->auth->group_info['addPost'] == 1)
		{
			$title = filter($_POST['title']);
			$translit = translit($title);
			$short = $_POST['short'];
			$xfield = isset($_POST['xfield']) ? $_POST['xfield'] : '';
			$xfieldT = isset($_POST['xfieldT']) ? ($_POST['xfieldT']) : '';
			$qTr = $db->query("SELECT * FROM ".DB_PREFIX."_news WHERE altname = '" . $db->safesql($translit) . "'");
			if($db->numRows($qTr) > 0) $translit = $translit.gencode(3);
			$category = isset($_POST['category']) ? array_unique($_POST['category']) : '0';
	
			if(is_array($category)) 
			{
				if(array_search(0, $category))
				{
					unset($category[array_serach(0, $category)]);
				}
				$firstCat = $category[0];
				unset($category[0]);
				$deleteCat = array_search($firstCat, $category);
				unset($category[$deleteCat]);
				$category[0] = $firstCat;
				ksort($category);
				$cats = '';
				foreach($category as $cid) 
				{
					$cats .= intval($cid) . ",";
				}
			}
			else 
			{
				$cats  = $category . ',';
			}
			
			$cats = ',' . $cats;
			
			set_title(array(_NEWS, _ADD_NEWS));			

			if(!empty($title) && !empty($short))
			{
				$fieldsSer = '';
				if(!empty($xfield))
				{
					foreach($xfield as $xId => $xContent)
					{
						if(!empty($xContent) && $xId > 0 && !empty($xfieldT[$xId]))
						{
							$xContent = processText(filter($xContent, 'html'));
							$xId = intval($xId);
							$xfieldT[$xId] = processText(filter($xfieldT[$xId], 'title'));
							$fileds[$xId] = array($xfieldT[$xId], $xContent);
						}
					}
					
					$fieldsSer = serialize($fileds);
				}
				
				$insert = $db->query("INSERT INTO `" . DB_PREFIX . "_news` ( `id` , `author` , `date` , `tags` , `cat` , `altname` , `allow_comments` , `allow_rating` , `allow_index` , `score` , `votes` , `views` , `comments` , `fields` , `groups` , `fixed` , `active` ) VALUES (NULL, '" . $core->auth->user_info['nick'] . "', '" . time() . "', '', '" . $cats . "', '" . $translit . "', '1', '1', '1', '0', '0', '0', '0', '" . $fieldsSer . "', ',0,', '0', '2');");
				$query = $db->query("SELECT * FROM ".DB_PREFIX."_news WHERE altname = '" . $db->safesql($translit) . "'");
				$news = $db->getRow($query);
				$short = fileInit('news', $news['id'], 'content', parseBB(processText(filter($_POST['short'], 'html')), $news['id']), 'user_temp'.$core->auth->user_id);
				$full = fileInit('news', $news['id'], 'content', parseBB(processText(filter($_POST['full'], 'html')), $news['id']), 'user_temp'.$core->auth->user_id);
				$db->query("INSERT INTO `" . DB_PREFIX . "_langs` ( `postId` , `module` , `title` , `short` , `full` , `lang` ) VALUES ('" . $news['id'] . "', 'news', '" . $db->safesql(processText($title)) . "', '" . $db->safesql($short) . "', '" . $db->safesql($full) . "' , '" . $config['lang'] . "');");
				fileInit('news', $news['id'], 'dir', '', 'user_temp'.$core->auth->user_id);
				$core->tpl->info("Ваша новость упешно добавлена. Ожидайте модерации, если все поля заполнены верно ваша новость благополучно попадёт в новостную ленту нашего портала.");
			}
			else
			{
				$core->tpl->info("Обязательные поля формы пусты! Вернитесь назад и попробуйте снова!");
			}
		}
		break;
	
	case "cat":
		main($url[2]);
	break;
	
	case "date":
		main('date');
	break;
	
	case "view":
		view();
		break;
		
	case 'edit':
		if($core->auth->isAdmin)
		{
			location(ADMIN.'/module/news/edit/'.$url[2]);
		}
		break;
		
	case 'delete':
		if($core->auth->isAdmin)
		{
			location(ADMIN.'/module/news/delete/'.$url[2]);
		}
		break;
		

	
	case "tags":
		if(isset($url[2])) 
		{
			main('tag');
		}
		else
		{
			set_title(array(_NEWS, _TAGS));
			
			
			$tag_query = $db->query("SELECT tag FROM " . DB_PREFIX . "_tags");

			while($tag = $db->getRow($tag_query)) 
			{
				$tags[] = $tag['tag'];
			}

			$tag_cloud = new TagsCloud;
			$tags_list = $tag_cloud->get_cloud($tags); 
			$core->tpl->title(_TAGS_ALL . ': ' . count($tags_list));
			$core->tpl->open();
			
			echo '<div align="center">' . "\n";
			
			foreach ($tags_list as $tag) 
			{
				echo $tag.' ';
			}
			
			echo '</div>' . "\n";
			$core->tpl->close();
		}
		break;

		
	/*case 'rss':
		echo 'rss';
		break;*/
}