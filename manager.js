/**
 * ManagerController.
 * 
 * @package	isc-dhcp-configurator
 * @author	SBF
 */
function ManagerController($scope, $http) {
	
	// intialise models
	$scope.parameters = [];
	$scope.reservations = [];
	$scope.fileList = [];
	
	// set initial states
	$scope.showEditor = false;
	$scope.loadedFile = { text: 'No file loaded' };
	$scope.showFileList = false;
	
	// retrieve list of existing files
	$http.post('api.php', {method: 'listFiles'})
		.success(function (data) {
			$scope.fileList = data.list;
			$scope.showFileList = true;
		}).error(function (data, status) {
			alert(data.code+': '+data.error);
		});
	
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
		$http.post('api.php', {
			
			method: 'createFile',
			label: $scope.newFileLabel.text
			
		}).success(function (data, status) {
			
			// hide modal
			$('#newFileModal').modal('hide');
			
			// add new file to list and show the list
			var timestamp = Math.round(new Date().getTime() / 1000);
			$scope.fileList.push({id: data.id, label: $scope.newFileLabel.text, updated: timestamp});
			$scope.showFileList = true;
			
		}).error(function (data, status) {
			
			alert(data.code+': '+data.error);
			
		});
		
	}
	
	// load selected file
	$scope.loadFile = function($index) {

		// retrieve file ID
		var fileID = $scope.fileList[$index].id;
		
		// API call
		$http.post('api.php', {
			
			method: 'loadFile',
			id: $scope.fileList[$index].id
			
		}).success(function (data, status) {
			
			
			
			// show editor
			$scope.loadedFile = { text: $scope.fileList[$index].label };
			$scope.showEditor = true;
			
		}).error(function (data, status) {
			
			alert(data.code+': '+data.error);
			
		});
		
	}

	// delete selected file
	$scope.deleteFile = function($index) {

		if (confirm('Are you sure you wish to delete the file "'+$scope.fileList[$index].label+'"?')) {
			
			$http.post('api.php', {
				method: 'deleteFile',
				id: $scope.fileList[$index].id
			}).success(function (data, status) {
				$scope.fileList.splice($index, 1);
			}).error(function (data, status) {
				alert(data.code+': '+data.error);
			});
			
		}
		
	}
	
	// add new parameter
	$scope.newParameter = function() {
		
		
		
	}
	
	// add new reservation
	$scope.newReservation = function() {
		
		
		
	}

}
