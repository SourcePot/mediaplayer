<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\MediaPlayer;

class MediaPlayer implements \SourcePot\Datapool\Interfaces\App{
	
	private $oc;
	
	private $entryTable;
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );

	public $definition=array('EntryId'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@Write'=>0));

	public function __construct($oc){
		$this->oc=$oc;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

	public function init(array $oc){
		$this->oc=$oc;
		$this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		$oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
	}

	public function getEntryTable(){
		return $this->entryTable;
	}
	
	public function getEntryTemplate(){
		return $this->entryTemplate;
	}

	public function run(array|bool $arr=TRUE):array{
		if ($arr===TRUE){
			return array('Category'=>'Apps','Emoji'=>'&#9835;','Label'=>'MediaPlayer','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
		} else {
			$html='';
			$this->getPlaylistIndexFormProcessing($arr);
			$arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
			$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
			if (empty($selector['Folder'])){
				$html.=$this->getPlaylistIndex(array('selector'=>$selector));
			} else {
                $selector['disableAutoRefresh']=TRUE;
				$settings=array('method'=>'getPlaylist','classWithNamespace'=>__CLASS__);
				$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('MediaPlayer settings container','generic',$selector,$settings,array('style'=>array('width'=>'auto','clear'=>'left')));
				$html.=$this->getVideoContainer(array('selector'=>$selector));
			}
			$html.=$this->embedCss();
			$html.=$this->embedJs();
			$arr['toReplace']['{{content}}']=$html;
			return $arr;
		}
	}

	public function getPlaylist($arr,$isDebugging=FALSE){
		$debugArr=array('arr'=>$arr);
		$mediaOptions=$this->getMediaOptions($arr);
		$contentStructure=array('Media'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$mediaOptions,'keep-element-content'=>TRUE),
								);
		$arr['selector']=$this->mediaPlayerEntryTemplate($arr,FALSE);
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Playlist '.$arr['selector']['Group'].' &rarr; '.$arr['selector']['Folder'];
		$arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		if ($isDebugging){
			$debugArr['selector']=$arr['selector'];
			$debugArr['contentStructure']=$arr['contentStructure'];
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $arr;
	}

	public function getMediaOptions(){
		$options=array(''=>'Please select...');
		$s=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$selector=array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Multimedia']->getEntryTable(),'Params'=>'%video%');
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector) as $mediaEntry){
			$key=$mediaEntry['Source'].$s.$mediaEntry['EntryId'];
			$options[$key]=$mediaEntry['Name'].' &rarr; '.$mediaEntry['Folder'].' &rarr; '.$mediaEntry['Group'];
		}
		return $options;
	}

	private function mediaPlayerEntryTemplate($arr){
		$entry=$arr['selector'];
		$entry['Type']=$entry['Source'].' array';
		$entry['Name']='Play list entry';
		$entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name'),'0','',FALSE);
		return $entry;
	}

	private function getVideoContainer($arr){
		$s=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
		$firstArr=FALSE;
		$matrix=array('playerHtml'=>array('html'=>''),'cntrHtml'=>array('html'=>''),'aHtml'=>array('html'=>''));
		$selector=$this->mediaPlayerEntryTemplate($arr);
		$selector['EntryId']=FALSE;
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','EntryId',TRUE) as $playListEntry){
			$mediaEntryArr=explode($s,$playListEntry['Content']['Media']);
			$mediaEntry=array('Source'=>$mediaEntryArr[0],'EntryId'=>$mediaEntryArr[1]);
			$mediaEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($mediaEntry,TRUE);
			$videoFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($mediaEntry);
			if (is_file($videoFile)){
				$absFile=$tmpDir.$mediaEntry['EntryId'].'.'.$mediaEntry['Params']['File']['Extension'];
				copy($videoFile,$absFile);
				$href=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($absFile);
				if (empty($firstArr)){$firstArr=array('src'=>$href,'type'=>$mediaEntry['Params']['File']['MIME-Type']);}
				$videoArr=array('tag'=>'a','href'=>$href,'type'=>$mediaEntry['Params']['File']['MIME-Type'],'element-content'=>$mediaEntry['Name'],'source'=>$mediaEntryArr[0],'entry-id'=>$mediaEntryArr[1],'class'=>'playlist','target'=>'_blank');
				$matrix['aHtml']['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($videoArr);
			}
		}
        if (empty($firstArr)){return '';}
		// medie player
		$sourceArr=array('tag'=>'source','id'=>'player-source','src'=>$firstArr['src'],'type'=>$firstArr['type']);
		$matrix['playerHtml']['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($sourceArr);
		$videoArr=array('tag'=>'video','element-content'=>$matrix['playerHtml']['html'],'keep-element-content'=>TRUE,'class'=>'mediaplayer','id'=>'player','controls'=>TRUE);
		$matrix['playerHtml']['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($videoArr);
		// media player control
		$playBtn=array('tag'=>'button','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'play','keep-element-content'=>TRUE);
		$playBtn['element-content']='&#10096;&#10096;';
		$playBtn['title']='Play whole list descending';
		$playBtn['id']='play-descending';
		$playBtn['key']=array($playBtn['id']);
		$matrix['cntrHtml']['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($playBtn);
		$playInfo=array('tag'=>'p','class'=>'play','element-content'=>'...');
		$matrix['cntrHtml']['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($playInfo);
		$playBtn['element-content']='&#10097;&#10097;';
		$playBtn['title']='Play whole list ascending';
		$playBtn['id']='play-ascending';
		$playBtn['key']=array($playBtn['id']);
		$matrix['cntrHtml']['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($playBtn);
		$cntrWrapper=array('tag'=>'div','element-content'=>$matrix['cntrHtml']['html'],'keep-element-content'=>TRUE,'class'=>'play-btn-wrapper');
		$matrix['cntrHtml']['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($cntrWrapper);
		// finalizing
		$html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Player (use play buttons below to start playing the whole play list)','hideKeys'=>TRUE,'hideHeader'=>TRUE));
		return $html;
	}

	private function getPlaylistIndexFormProcessing(){
		$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'getPlaylistIndex');
		if (isset($formData['cmd']['select'])){
			$selector['Group']=key($formData['cmd']['select']);
			$selector['Folder']=key($formData['cmd']['select'][$selector['Group']]);
			$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState(__CLASS__,$selector);
		}
		return $selector;
	}
	
	private function getPlaylistIndex($arr){
		// get all playlist entries
		$s=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$playLists=array();
		$arr['selector']['Name']='Play list entry';
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr['selector']) as $playListEntry){
			$mediaEntryArr=explode($s,$playListEntry['Content']['Media']);
			$mediaEntry=array('Source'=>$mediaEntryArr[0],'EntryId'=>$mediaEntryArr[1]);
			$mediaEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($mediaEntry,TRUE);
			$playLists[$playListEntry['Group']][$playListEntry['Folder']][$playListEntry['EntryId']]=$mediaEntry['Name'];
		}
		// compile html
		$arr['html']='';
		if (empty($playLists)){
			$text='No playlists could be found.<br/>Either there are no lists yet or the access rights are not sufficient.';
			$arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$text,'keep-element-content'=>TRUE,'class'=>'playlist'));
		} else {
			ksort($playLists);
			foreach($playLists as $group=>$folders){
				$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h1','element-content'=>$group,'keep-element-content'=>TRUE));
				ksort($folders);
				foreach($folders as $folder=>$entries){
					$selectBtn=array('tag'=>'button','element-content'=>$folder,'key'=>array('select',$group,$folder),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'playlist-select');
					$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($selectBtn);
					ksort($entries);
					$folderHtml='';
					foreach($entries as $entryId=>$name){
						$entryArr=array('tag'=>'li','element-content'=>$name);
						$folderHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($entryArr);
					}
					$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'ol','element-content'=>$folderHtml,'keep-element-content'=>TRUE,'class'=>'playlist'));
				}
			}
		}
		$articleArr=array('tag'=>'article','element-content'=>$arr['html'],'keep-element-content'=>TRUE);
		$arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($articleArr);
		return $arr['html'];
	}

	private function embedJs(){
		$classWithNamespaceComps=explode('\\',__CLASS__);
		$class=array_pop($classWithNamespaceComps);
		$jsFile=__DIR__.'/'.$class.'.js';
		return '<script>'.file_get_contents($jsFile).'</script>';
	}

	private function embedCss(){
		$classWithNamespaceComps=explode('\\',__CLASS__);
		$class=array_pop($classWithNamespaceComps);
		$cssFile=__DIR__.'/'.$class.'.css';
		return '<style>'.file_get_contents($cssFile).'</style>';
	}

}
?>