/**
 * File gallery handler
 */
function FileGallery() {
	'use strict';
	
	var _gallery;
	var _pager;
	var _summary;
	var _selectedFiles = [];
	var _multiple = false;
	
	function initSelectedFiles(event) {
		var input = event.data.input;
		
		uncheckAll();
		
		if (undefined == input || '' == input.val()) {
			return;
		}
		
		if (_multiple) {
			_selectedFiles = JSON.parse(input.val());
		} else {
			_selectedFiles = [Number(input.val())];
		}
		
		markFiles(_selectedFiles);
	}
	
	function init(gallery) {
		_gallery = gallery;
		
		_pager = (new GalleryPager()).init(_gallery);
		_summary = (new GallerySummary()).init(_gallery, _pager);
		_multiple = _gallery.data('multiple');
		
		_gallery.on('click', '.pagination a', paginationClick);
		_gallery.on('click', '.media-file__link', mediaFileLinkClick);
		
		return this;
	}
	
	function paginationClick(event) {
		event.preventDefault();
		
		var link = $(event.currentTarget);
		
		_gallery.find('.gallery__items').load(link.attr('href'), markFiles);
		_pager.click(link);
	}
	
	function mediaFileLinkClick(event) {
		event.preventDefault();
		
		var item = $(event.currentTarget);
		
		click(item);
		toggleSelectedFiles(item);
	}
	
	function markFiles() {
		_selectedFiles.forEach(function(item) {
			var checkedItem = _gallery.find('.media-file[data-key="' + item + '"] .media-file__link').eq(0);
			click(checkedItem);
		});
	}
	
	function toggleSelectedFiles(item) {
		var imageId = item.closest('.media-file').data('key');
		
		if (_multiple) {
			var imageIdIndex = _selectedFiles.indexOf(imageId);
			
			if (-1 == imageIdIndex) { // not found
				_selectedFiles.push(imageId);
			} else {
				_selectedFiles.splice(imageIdIndex, 1);
			}
		} else {
			_selectedFiles[0] = imageId;
		}
	}
	
	function click(item) {
		if (_gallery.data('multiple')) {
			item.toggleClass('media-file__link_checked');
		} else if (item.hasClass('media-file__link_checked')) {
			item.removeClass('media-file__link_checked');
		} else {
			uncheckAll();
			item.addClass('media-file__link_checked');
		}
		
		item.closest('.media-file').trigger('media-file-click');
		
		return this;
	};
	
	function uncheckAll() {
		_gallery.find('.media-file__link').removeClass('media-file__link_checked');
	}
	
	function uploadFromNextPage() {
		$.ajax({
			type: "POST",
			data: 'page=' + _pager.getCurrentPage(),
			url: _gallery.closest('.file-manager').data('base-url') + '/next-page-file',
			success: function(response) {
				if (!response.success) {
					return;
				}
				
				_gallery.find('.gallery-items').eq(0).append(response.html);
				
				uncheckAll();
				markFiles();
			}
		});
	}
	
	function isChecked(item) {
		return !!item.find('.media-file__link_checked').length;
	}
	
	function getCheckedItems() {
		return _gallery.find('.media-file__link_checked').closest('.media-file');
	}
	
	function isMultiple() {
		return _multiple;
	}
	
	function getSelectedFiles() {
		return _selectedFiles;
	}
	
	function deleteItem(id, pagination) {
		_gallery.find('[data-key="' + id + '"]').fadeOut(function() {
			$(this).remove();
			uploadFromNextPage();
			_pager.update(pagination);
			_summary.update(pagination);
		});
	}
	
	return {
		'deleteItem': deleteItem,
		'getCheckedItems': getCheckedItems,
		'getSelectedFiles': getSelectedFiles,
		'init': init,
		'initSelectedFiles': initSelectedFiles,
		'isChecked': isChecked,
		'isMultiple': isMultiple
	}
}
