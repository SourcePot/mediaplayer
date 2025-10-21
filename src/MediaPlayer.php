<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://opensource.org/license/mit MIT
*/
declare(strict_types=1);

namespace SourcePot\MediaPlayer;

class MediaPlayer implements \SourcePot\Datapool\Interfaces\App{
	
	private const ONEDIMSEPARATOR='|[]|';
	private const NO_PLAYLISTS='No playlists could be found.<br/>Either there are no lists yet, or you do not have sufficient access rights.';
	private const NO_ENTRY='No entries yet..';
	private const NO_AVAILABLE_ENTRY='Access restricted...';

    private $oc;
	
	private $entryTable='';
	private $entryTemplate=[
		'Read'=>[
			'index'=>FALSE,
			'type'=>'SMALLINT UNSIGNED',
			'value'=>'ALL_MEMBER_R',
			'Description'=>'This is the entry specific Read access setting. It is a bit-array.'
		],
	];

	public $definition=[
		'EntryId'=>['@tag'=>'input','@type'=>'text','@default'=>'','@Write'=>0]
	];

	public function __construct($oc){
		$this->oc=$oc;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

	public function init()
	{
		$this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
		$this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
	}

	public function getEntryTable(){
		return $this->entryTable;
	}
	
	public function getEntryTemplate(){
		return $this->entryTemplate;
	}

	public function run(array|bool $arr=TRUE):array{
		if ($arr===TRUE){
			return ['Category'=>'Apps','Emoji'=>'&#9835;','Label'=>'MediaPlayer','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__];
		} else {
			$html='';
			$this->getPlaylistIndexFormProcessing($arr);
			$arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__,['EntryId'=>FALSE,'miscToolsEntry'=>FALSE,'settingsEntry'=>FALSE,'setRightsEntry'=>FALSE,'sendEmail'=>FALSE],FALSE);
			$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
			if (empty($selector['Folder'])){
				$html.=$this->getPlaylistIndex(['selector'=>$selector]);
			} else {
            	$html.=$this->getVideoContainer(['selector'=>$selector]);
			    $selector['disableAutoRefresh']=TRUE;
				$settings=['method'=>'getPlaylist','classWithNamespace'=>__CLASS__];
				$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('MediaPlayer play list','generic',$selector,$settings,['style'=>['clear'=>'none','width'=>'fit-content','border'=>'none']]);
			}
			$html.=$this->embed('css');
			$html.=$this->embed('js');
			$arr['toReplace']['{{content}}']=$html;
			return $arr;
		}
	}

	public function getPlaylist($arr,$isDebugging=FALSE){
		$debugArr=['arr'=>$arr];
		$mediaOptions=$this->getMediaOptions($arr);
		$contentStructure=['Media'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$mediaOptions,'keep-element-content'=>TRUE],];
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
		$options=[''=>'Please select...'];
		$selector=['Source'=>$this->oc['SourcePot\Datapool\GenericApps\Multimedia']->getEntryTable(),'Params'=>'%video%'];
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector) as $mediaEntry){
			$key=$mediaEntry['Source'].(self::ONEDIMSEPARATOR).$mediaEntry['EntryId'];
			$options[$key]=$mediaEntry['Name'].' &rarr; '.$mediaEntry['Folder'].' &rarr; '.$mediaEntry['Group'];
		}
		return $options;
	}

	private function mediaPlayerEntryTemplate($arr){
		$entry=$arr['selector'];
		$entry['Name']='Play list entry';
		$entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],'0','',FALSE);
		return $entry;
	}

	private function getVideoContainer($arr){
		$tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
		$firstArr=FALSE;
		$entryExists=FALSE;
		$matrix=['playerHtml'=>['html'=>''],'cntrHtml'=>['html'=>''],'aHtml'=>['html'=>'']];
		$mediaPlayerEntry=$this->mediaPlayerEntryTemplate($arr);
		$selector=['Source'=>$mediaPlayerEntry['Source'],'EntryId'=>'%'.$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListKeyFromEntryId($mediaPlayerEntry['EntryId'])];
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','EntryId',TRUE) as $playListEntry){
			if (empty($playListEntry['Content']['Media'])){continue;}
			$entryExists=TRUE;
			$mediaEntryArr=explode(self::ONEDIMSEPARATOR,$playListEntry['Content']['Media']??'');
			$mediaEntry=['Source'=>$mediaEntryArr[0],'EntryId'=>$mediaEntryArr[1]];
			$mediaEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($mediaEntry,FALSE);
			if (empty($mediaEntry)){continue;}
			$videoFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($mediaEntry);
			if (is_file($videoFile)){
				$absFile=$tmpDir.$mediaEntry['EntryId'].'.'.$mediaEntry['Params']['File']['Extension'];
				copy($videoFile,$absFile);
				$href=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($absFile);
				if (empty($firstArr)){
					$firstArr=['src'=>$href,'type'=>$mediaEntry['Params']['File']['MIME-Type']];
				}
				$videoArr=['tag'=>'a','href'=>$href,'type'=>$mediaEntry['Params']['File']['MIME-Type'],'element-content'=>$mediaEntry['Name'],'source'=>$mediaEntryArr[0],'entry-id'=>$mediaEntryArr[1],'class'=>'playlist','target'=>'_blank'];
				$matrix['aHtml']['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($videoArr);
			}
		}
        if (empty($firstArr)){
			if ($entryExists){
				return $this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>self::NO_AVAILABLE_ENTRY,'keep-element-content'=>TRUE,'class'=>'playlist']);
			} else {
				return $this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>self::NO_ENTRY,'keep-element-content'=>TRUE,'class'=>'playlist']);
			}
		}
		// media player
		$sourceArr=['tag'=>'source','id'=>'player-source','src'=>$firstArr['src'],'type'=>$firstArr['type']];
		$matrix['playerHtml']['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($sourceArr);
		$videoArr=['tag'=>'video','element-content'=>$matrix['playerHtml']['html'],'keep-element-content'=>TRUE,'class'=>'mediaplayer','id'=>'player','controls'=>TRUE];
		$matrix['playerHtml']['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($videoArr);
		// media player control
		$playBtn=['tag'=>'button','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'play','keep-element-content'=>TRUE];
		$playBtn['element-content']='&#10096;&#10096;';
		$playBtn['title']='Play whole list descending';
		$playBtn['id']='play-descending';
		$playBtn['key']=[$playBtn['id']];
		$matrix['cntrHtml']['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($playBtn);
		$playInfo=['tag'=>'p','class'=>'play','element-content'=>'...'];
		$matrix['cntrHtml']['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($playInfo);
		$playBtn['element-content']='&#10097;&#10097;';
		$playBtn['title']='Play whole list ascending';
		$playBtn['id']='play-ascending';
		$playBtn['key']=[$playBtn['id']];
		$matrix['cntrHtml']['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($playBtn);
		$cntrWrapper=['tag'=>'div','element-content'=>$matrix['cntrHtml']['html'],'keep-element-content'=>TRUE,'class'=>'play-btn-wrapper'];
		$matrix['cntrHtml']['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($cntrWrapper);
		// finalizing
		$html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Use play buttons "&#10096;&#10096;" or "&#10097;&#10097;" to start the play list.','hideKeys'=>TRUE,'hideHeader'=>TRUE]);
		$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>['clear'=>'none','width'=>'fit-content','border'=>'none']]);
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
		$playLists=[];
		$arr['selector']['Name']='Play list entry';
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr['selector']) as $playListEntry){
			if (empty($playListEntry['Content']['Media'])){continue;}
			$mediaEntryArr=explode(self::ONEDIMSEPARATOR,$playListEntry['Content']['Media']);
			$mediaEntry=['Source'=>$mediaEntryArr[0],'EntryId'=>$mediaEntryArr[1]];
			$mediaEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($mediaEntry,TRUE);
			$playLists[$playListEntry['Group']][$playListEntry['Folder']][$playListEntry['EntryId']]=$mediaEntry['Name']??'';
		}
		// compile html
		$arr['html']='';
		if (empty($playLists)){
			$arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>self::NO_PLAYLISTS,'keep-element-content'=>TRUE,'class'=>'playlist']);
		} else {
			ksort($playLists);
			foreach($playLists as $group=>$folders){
				$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h1','element-content'=>$group,'keep-element-content'=>TRUE]);
				ksort($folders);
				foreach($folders as $folder=>$entries){
					$selectBtn=['tag'=>'button','element-content'=>$folder,'key'=>['select',$group,$folder],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'playlist-select'];
					$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($selectBtn);
					ksort($entries);
					$folderHtml='';
					foreach($entries as $entryId=>$name){
						$entryArr=['tag'=>'li','element-content'=>$name];
						$folderHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($entryArr);
					}
					$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'ol','element-content'=>$folderHtml,'keep-element-content'=>TRUE,'class'=>'playlist']);
				}
			}
		}
		$articleArr=['tag'=>'article','element-content'=>$arr['html'],'keep-element-content'=>TRUE];
		$arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($articleArr);
		return $arr['html'];
	}

	private function embed($extension='js'){
		$classWithNamespaceComps=explode('\\',__CLASS__);
		$class=array_pop($classWithNamespaceComps);
		$elArr=['tag'=>(($extension==='js')?'script':'style'),'keep-element-content'=>TRUE];
		$elArr['element-content']=file_get_contents(__DIR__.'/'.$class.'.'.$extension);
		return PHP_EOL.$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr).PHP_EOL;
	}

}
?>