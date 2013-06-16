/**
 * ManagerController.
 * 
 * Controls the configuration file manager.
 * 
 * @package	isc-dhcp-configurator
 * @author	SBF
 */
function ManagerController($scope, $http) {
	
	// list of existing files
	$scope.fileList = [
		{
			id:		1,
			label:		'test',
			updated:	'test 2'
		}
	];
	
	// show file list?
	if ($scope.fileList.length > 0) {
		$scope.showFileList = true;
	} else {
		$scope.showFileList = false;
	}
	
	// new file label text box model
	$scope.newFileLabel = {
		text : '',
		validation : ''
	}
	
	// open new file modal. I know there's a better way of doing this without
	// jQuery and more Angular but I've tried several methods which purport to
	// work and they haven't and I don't need this bit holding me up at this stage
	$scope.openNewFileModal = function() {
		
		$scope.newFileLabel.validation = '';
		$('#newFileLabel').popover('hide');
		
		$('#newFileModal').modal({
			backdrop: false,
			show: true
		});
		
	}
	
	// handle request to create a new configuration file
	$scope.createNewFile = function() {
		
		// new file label must be at least one character in length
		if (typeof $scope.newFileLabel.text === 'undefined' || new String($scope.newFileLabel.text).length == 0) {
			$scope.newFileLabel.validation = 'error';
			$('#newFileLabel').popover('show');
			return;
		}
		
		// API call
		$http({
			
			method:	'POST',	url: 'api.php',
			data: { method: 'create', label: $scope.newFileLabel.text }
			
		}).success(function (data, status) {
			
			$('#newFileModal').modal({ show: false});
			$scope.showFileList = true;
			
		}).error(function (data, status) {
			
			alert('Unable to create new configuration file.')
			
		});
		
	}

}
