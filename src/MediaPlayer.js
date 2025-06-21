jQuery(document).ready(function(){
	let playList=[],playIndex=-1,playOrder=false;
	
	loadPlayList();
	function loadPlayList(){
		jQuery('a.playlist').each(function(i){
			playList[i]={'Source':jQuery(this).attr('source'),'EntryId':jQuery(this).attr('entry-id'),'src':jQuery(this).attr('href'),'type':jQuery(this).attr('type')};
		});
		jQuery('p.play').html((playIndex+2)+'/'+playList.length);
	}

	jQuery('button.play').on('click',function(e){
		e.preventDefault();
		jQuery('button.play').css('backgroundColor','');
		playOrder=jQuery(this).attr('id');
		jQuery(this).css('backgroundColor','#fcc');
		loadNext();
	});
	
	function loadNext(){
		if (playList.length>0){
			if (playIndex<0){
				playIndex=0;
			} else {
				if (playOrder.localeCompare('play-ascending')==0){
					if (playIndex==(playList.length-1)){
						playIndex=0;
					} else {
						playIndex++;
					}
				} else {
					if (playIndex>0){
						playIndex--;
					} else {
						playIndex=(playList.length-1);
					}	
				}
			}
			media=playList[playIndex];
			playNext(media);
			var label=jQuery('a[entry-id='+media['EntryId']+']').html();
			jQuery('p.play').html(label);
		}
	}
	
	function playNext(media){
		let video=jQuery('#player')[0];
		jQuery('#player-source').attr({'src':media['src'],'type':media['type']});
		video.load();
		video.addEventListener("loadeddata",function(){
			video.removeEventListener('loadeddata',this);
			video.play();	
			video.addEventListener("ended",function(e){
				video.removeEventListener('ended',this);
				e.stopImmediatePropagation();
				loadNext();	
			});
		});
		return false;
	}

});