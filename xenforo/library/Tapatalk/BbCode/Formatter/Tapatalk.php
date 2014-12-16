<?php
  
class Tapatalk_BbCode_Formatter_Tapatalk extends XenForo_BbCode_Formatter_Base
{
	
	protected $returnHtml = true;
	
	protected $_simpleReplacements = array(
		'left' => "%s\n",
		'center' => "%s\n",
		'indent' => "    %s\n",
		'right' => "%s\n"
	);
	
	protected $_advancedReplacements = array(
		'code' => array('$this', 'handleTagCode'),
		'php' => array('$this', 'handleTagPHP'),
		'html' => array('$this', 'handleTagHtml'),
		'quote' => array('$this', 'handleTagQuote'),
		'img' => array('$this', 'renderTagUnparsed'),
		'url' => array('$this', 'handleTagUrl'),
		'tex' => array('$this', 'handleTagTex'),
		'attach' => array('$this', 'handleTagAttach'),
		'media' => array('$this', 'handleTagMedia'),
		'list' => array('$this', 'handleTagList'),
		'spoiler' => array('$this', 'handleTagSpoiler'),
        'color' => array('$this', 'handleTagColor'),
	);
	
	protected $_ttMediaSites = array(
		'youtube' => 'http://www.youtube.com/watch?v={$id}',
		'vimeo' => 'http://www.vimeo.com/{$id}',
		'facebook' => 'http://www.facebook.com/video/video.php?v={$id}',
	);
				
	public function __construct($returnHtml = false)
	{
		$this->_returnHtml = $returnHtml;
		$this->_tags = $this->getTags();
		$this->preLoadData();
	}
	
	public function getTags()
	{
		if ($this->_tags !== null)
		{
			return $this->_tags;
		}

		$callback = array($this, 'handleTag');

		$tags = parent::getTags();
		$tags['tex'] = array(
			'hasOption' => false,
			'plainChildren' => true
		);
		foreach ($tags AS $tagName => &$tag)
		{			
			if($this->_returnHtml){
				switch($tagName){
					case 'b':
					case 'i':
						break;
					case 'u':
						$tag['replace'] = array('<u>', '</u>');
						break;
					case 'color':
						$tag['replace'] = array('<font color="%s">', '</font>');
					default:
						unset($tag['replace'], $tag['callback']);
						$tag['callback'] = $callback;
						break;
				}
			} else {
				unset($tag['replace'], $tag['callback']);
				$tag['callback'] = $callback;                				
			}
		}
		return $tags;
	}
	
	public function handleTagUrl(array $tag, array $rendererStates)
	{
		if (!empty($tag['option']))
		{
			$url = $tag['option'];
			$text = $this->renderSubTree($tag['children'], $rendererStates);
		}
		else
		{
			$url = $this->stringifyTree($tag['children']);
			$text = urldecode($url);
			if (!utf8_check($text))
			{
				$text = $url;
			}
			$text = XenForo_Helper_String::censorString($text);

			if (!empty($rendererStates['shortenUrl']))
			{
				$length = utf8_strlen($text);
				if ($length > 100)
				{
					$text = utf8_substr_replace($text, '...', 35, $length - 35 - 45);
				}
			}

			$text = htmlspecialchars($text);
		}

		$url = $this->_getValidUrl($url);
		if (!$url)
		{
			return $text;
		}
		else
		{
			$url = XenForo_Helper_String::censorString($url);

			return "[url={$url}]{$text}[/url]";
		}
	}
	
	public function handleTagTex(array $tag, array $rendererStates)
	{
		$tex = $this->stringifyTree($tag['children']);    
		$url = XenForo_Link::convertUriToAbsoluteUri("cgi-bin/mathtex.cgi?tapatalk=1&formula={$tex}", true);
		return "[img]{$url}[/img]";
	}
	
	public function filterString($string, array $rendererStates)
	{
		$string = XenForo_Helper_String::censorString($string);

		return $string;
	}

	public function handleTagMedia(array $tag, array $rendererStates)
	{
		$mediaKey = trim($this->stringifyTree($tag['children']));
		if (preg_match('#[&?"\'<>]#', $mediaKey) || strpos($mediaKey, '..') !== false)
		{
			return '';
		}

		$mediaSiteId = strtolower($tag['option']);
		if (isset($this->_ttMediaSites[$mediaSiteId]))
		{
			$embedHtml = $this->_ttMediaSites[$mediaSiteId];
			return "[url]".str_replace('{$id}', urlencode($mediaKey), $embedHtml)."[/url]";
		}
		else
		{
			return "Unsupported video ([media={$mediaSiteId}]{$mediaKey}[/media])";
		}
	}

	
	public function handleTag(array $tag, array $rendererStates)
	{
		$tagName = $tag['tag'];

		if (isset($this->_advancedReplacements[$tagName]))
		{
			$callback = $this->_advancedReplacements[$tagName];
			if (is_array($callback) && $callback[0] == '$this')
			{
				$callback[0] = $this;
			}

			return call_user_func($callback, $tag, $rendererStates);
		}

		$output = $this->renderSubTree($tag['children'], $rendererStates);

		if (isset($this->_simpleReplacements[$tagName]))
		{
			$output = sprintf($this->_simpleReplacements[$tagName], $output);
		}

		return $output;
	}
	
	public function handleTagAttach(array $tag, array $rendererStates)
	{
		$id = intval($this->stringifyTree($tag['children']));
		if (!$id)
		{
			return '';
		}
		
		if (empty($rendererStates['attachments'][$id]))
		{
			$attachment = array('attachment_id' => $id);
			$validAttachment = false;
			$canView = false;
		}
		else
		{
			$attachment = $rendererStates['attachments'][$id];
			$validAttachment = true;
			$canView = empty($rendererStates['viewAttachments']) ? false : true;
		}
		
		if(!$validAttachment){
			
			$output = "[url=".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment), true)."]".
			new XenForo_Phrase('view_attachment_x', array('name' => $attachment['attachment_id']))."[/url]";
			
		} elseif(empty($attachment['thumbnailUrl'])){
			
			$output = "[url=".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment), true)."]".
			new XenForo_Phrase('view_attachment_x', array('name' => $attachment['filename']))."[/url]";			
			
		} elseif($canView && strtolower($tag['option']) == 'full'){
			
			$output = "[img]".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment, array('embedded' => '1')), true)."[/img]";
			
		} elseif($canView){
			
			/*$output = "[url=".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment, array('embedded' => '1')), true)."][img]".
			XenForo_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl'], true)."[/img][/url]";*/			
			$output = "[img]".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment, array('embedded' => '1')), true)."[/img]";
			
		} else {
			
			/*$output = "[url=".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment), true)."][img]".
			XenForo_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl'], true)."[/img][/url]";      */			
			$output = "[url=".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment), true)."]".
			new XenForo_Phrase('view_attachment_x', array('name' => $attachment['filename']))."[/url]";         
			
		}
		
		return $output;
	}
	
	public function handleTagList(array $tag, array $rendererStates)
	{
		$bullets = explode('[*]', trim($this->renderSubTree($tag['children'], $rendererStates)));

		$output = "\n";
		foreach ($bullets AS $bullet)
		{
			$bullet = trim($bullet);
			if ($bullet !== '')
			{
				$output .= " - ".$bullet . "\n";
			}
		}
		$output .= "\n";

		return $output;
	}
	
	public function handleTagQuote(array $tag, array $rendererStates)
	{
		if (empty($rendererStates['quoteDepth']))
		{
			$rendererStates['quoteDepth'] = 1;
		}
		else
		{
			$rendererStates['quoteDepth']++;
		}
/*
		if ($this->_maxQuoteDepth > -1 && $rendererStates['quoteDepth'] > $this->_maxQuoteDepth)
		{
			return '';
		}*/
		
		if ($tag['option'])
		{
			$parts = explode(',', $tag['option']);
			$name = $this->filterString(array_shift($parts), $rendererStates);
			
			$tag['option'] = '';
			$tag['original'][0] = '[quote]';
		}
		else
		{
			$name = false;
		}
		
		if (!empty($tag['original']) && is_array($tag['original']))
		{
			list($prepend, $append) = $tag['original'];
		}
		else
		{
			$prepend = '';
			$append = '';
		}
		
		if(!empty($name)){
			$prepend_referer = new XenForo_Phrase('x_said', array('name' => $name)).": ";
			
			if (isset($rendererStates['returnHtml']) && $rendererStates['returnHtml'])
				$prepend .= "<b>{$prepend_referer}</b><br />";
			else
				$prepend .= "$prepend_referer\r\n";
		}

/*
		if ($rendererStates['quoteDepth'] == $this->_maxQuoteDepth)
		{
			// at the edge of the quote, so we want to ltrim whatever comes after
			foreach ($tag['children'] AS $key => $child)
			{
				if (is_array($child) && !empty($child['tag']) && $child['tag'] == 'quote' && isset($tag['children'][$key + 1]))
				{
					$after =& $tag['children'][$key + 1];
					if (is_string($after))
					{
						$after = ltrim($after);
					}
				}
			}
		}

		if ($this->_stripAllBbCode)
		{
			$prepend = '';
			$append = '';
		}*/

		return $this->filterString($prepend, $rendererStates)
			. $this->renderSubTree($tag['children'], $rendererStates)
			. $this->filterString($append, $rendererStates);
	}
	
	public function handleTagCode($tag, $rendererStates){
		if ($tag['option'])
		{
			$parts = explode(',', $tag['option']);
			$name = $this->filterString(array_shift($parts), $rendererStates);

			$tag['option'] = '';
			$tag['original'][0] = '[CODE]';
		}
		else
		{
			$name = false;
		}

		if (!empty($tag['original']) && is_array($tag['original']))
		{
			list($prepend, $append) = $tag['original'];
		}
		else
		{
			$prepend = '';
			$append = '';
		}

		if(!empty($name)){
			$prepend_referer = new XenForo_Phrase('x_said', array('name' => $name)).": ";

			if (isset($rendererStates['returnHtml']) && $rendererStates['returnHtml'])
				$prepend .= "<b>{$prepend_referer}</b><br />";
			else
				$prepend .= "$prepend_referer\r\n";
		}

		return $this->filterString($prepend, $rendererStates)
			. $this->renderSubTree($tag['children'], $rendererStates)
			. $this->filterString($append, $rendererStates);
	}

	public function handleTagPHP($tag, $rendererStates){
		$content = $this->renderSubTree($tag['children'], $rendererStates);
		$content = preg_replace('/\[(CODE|\/CODE)\]/', "[ $1]" , $content);
		return '[CODE]'
			. $content
			. '[/CODE]';
	}

	public function handleTagHtml($tag, $rendererStates){
		$content = $this->renderSubTree($tag['children'], $rendererStates);
		$content = preg_replace('/\[(CODE|\/CODE)\]/', "[ $1]" , $content);
		return '[CODE]'
			. $content
			. '[/CODE]';
	}

	public function handleTagSpoiler($tag, $rendererStates){
		$bullets = explode('[*]', trim($this->renderSubTree($tag['children'], $rendererStates)));

		$output = "\n";
		foreach ($bullets AS $bullet)
		{
			$bullet = trim($bullet);
			if ($bullet !== '')
			{
				$output .= "[spoiler]".$bullet . "[/spoiler]\n";
			}
		}
		$output .= "\n";
		return $output;
	}

    public function handleTagColor($tag, $rendererStates){
        $content = $this->renderSubTree($tag['children'], $rendererStates);
        return '<font color="'.$tag['option'].'">'.$content.'</font>';
    }
}