/**
 * ManagerController.
 * 
 * @package	isc-dhcp-configurator
 * @author	SBF
 * @version	1.00
 */

// create module
var app = angular.module('dhcp', ['directives']);

// main controller
app.controller('ManagerController', function($scope, $http) {
	
	// intialise models
	$scope.parameters = [];
	$scope.reservations = [];
	$scope.fileList = [];
	
	// set initial states
	$scope.loadedFile = false;
	$scope.showFileList = false;
	
	// retrieve list of existing files
	$http.post('api.php', {method: 'listFiles'})
		.success(function (data) {
			$scope.fileList = data.list;
			$scope.showFileList = true;
		}).error(function (data, status) {
			alert(data.code+': '+data.error);
		});
	
	// initialise file information model
	$scope.newFile = {
		label: '',	labelValid: '',
		subnet: '',	subnetValid: '',
		netmask: '',	netmaskValid: ''
	};
	
	// open new file modal. I know there's a better way of doing this without
	// jQuery and more Angular but I've tried several methods which purport to
	// work and they haven't and I don't need this bit holding me up at this stage
	$scope.openNewFileModal = function() {

		// reset file information model
		$scope.newFile = {
			label: '',	labelValid: '',
			subnet: '',	subnetValid: '',
			netmask: '',	netmaskValid: ''
		};
		
		$('INPUT.hasPopover').popover('hide');
		
		$('#newFileModal').modal({
			backdrop: false,
			show: true
		});
		
	}
	
	// handle request to create a new configuration file
	$scope.createNewFile = function() {
		
		// label must be at least one character in length
		if (typeof $scope.newFile.label === 'undefined' || new String($scope.newFile.label).length == 0) {
			$scope.newFile.labelValid = 'error';
			$('#newFileLabel').popover('show');
			return;
		}
		
		// subnet must be at least 7 characters in length
		if (typeof $scope.newFile.subnet === 'undefined' || new String($scope.newFile.subnet).length < 7) {
			$scope.newFile.subnetValid = 'error';
			$('#newFileSubnet').popover('show');
			return;
		}
		
		// netmask must be at least 7 characters in length
		if (typeof $scope.newFile.netmask === 'undefined' || new String($scope.newFile.netmask).length < 7) {
			$scope.newFile.netmaskValid = 'error';
			$('#newFileNetmask').popover('show');
			return;
		}
		
		// API call
		$http.post('api.php', {
			
			method: 'createFile',
			label: $scope.newFile.label,
			subnet: $scope.newFile.subnet,
			netmask: $scope.newFile.netmask
			
		}).success(function (data, status) {
			
			// hide modal
			$('#newFileModal').modal('hide');
			
			// add new file to list and show the list
			var timestamp = Math.round(new Date().getTime() / 1000);
			$scope.fileList.push({id: data.id, label: $scope.newFile.label, updated: timestamp});
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
			
			// insert data into model
			$scope.parameters = data.parameters;
			$scope.reservations = data.reservations;
			
			// add new lines (disabled until blank line remover is working properly)
		//	$scope.newParameter();
		//	$scope.newReservation();
			
			// show editor
			$scope.loadedFile = $scope.fileList[$index];
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
		$scope.parameters.push({param_key: '', param_val: '', notes: ''});
	}
	
	// add new reservation
	$scope.newReservation = function() {
		$scope.reservations.push({label: '', mac_address: '', ip_address: ''});
	}
	
	// delete parameter
	$scope.deleteParameter = function($index) {
		$scope.parameters.splice($index, 1);
	}
	
	// delete reservation
	$scope.deleteReservation = function($index) {
		$scope.reservations.splice($index, 1);
	}
	
	// save file to database
	$scope.saveFile = function() {
		
		// remove completely blank records from models
		$scope.parameters = $scope.removeBlanks($scope.parameters);
		$scope.reservations = $scope.removeBlanks($scope.reservations);
		
		// API call
		$http.post('api.php', {
			
			method: 'saveFile',
			file: $scope.loadedFile,
			parameters: $scope.parameters,
			reservations: $scope.reservations
			
		}).success(function (data, status) {
			
			$scope.loadedFile.updated = data.updated;
			alert('File saved successfully.');
			
		}).error(function (data, status) {
			alert(data.code+': '+data.error);
		});
		
	}
	
	// generate and export file
	$scope.generateFile = function() {
		
		// begin configuration file
		var config = '# ISC DHCP server configuration\n\n';
		config += '# Generated by https://github.com/stuartford/isc-dhcp-configurator/\n\n';
		
		// add basics
		config += 'ddns-update-style none;\n';
		config += 'ignore client-updates;\n\n';
		
		// open subnet
		config += 'subnet '+$scope.loadedFile.subnet+' netmask '+$scope.loadedFile.netmask+' {\n\n';
		
		// add parameters
		$scope.parameters.forEach(function(param) {
			if (param.param_key != '') {
				if (param.notes != '') config += '\t# '+param.notes+'\n';
				config += '\t'+param.param_key+' '+param.param_val+';\n';
			}
		});
		config += '\n';
		
		// add reservations
		config += '\t# static reservations\n';
		$scope.reservations.forEach(function(rsv) {
			if (rsv.label != '') {
				config += '\thost '+rsv.label+' { hardware ethernet '+rsv.mac_address+'; fixed-address '+rsv.ip_address+'; }\n';
			}
		});
		
		// close subnet
		config += '\n\n}\n';
		
		// show generated data in a new window
		window.open("data:text/plain," + escape(config));
		
	}
	
	/**
	 * Remove blank records from a model.
	 * 
	 * @todo this doesn't work properly and needs revisiting
	 */
	$scope.removeBlanks = function(srcModel) {
		
		// just return input until I've fixed this
		return srcModel;
		
		var newModel = new Array();
		
		$scope.parameters.forEach(function(row, i) {
			
			var keys = Object.keys(srcModel[i]);
			var blank = true;
			
			for (j=0; j<keys.length; j++) {
				if (keys[j] != '$$hashKey' && row[keys[j]] != '') blank = false;
			}
			
			if (!blank) newModel.push(srcModel[i]);
			
		});
		
		return newModel;
		
	}
	


});

// define directives
angular.module('directives', []).
	
	// check that input is unique across the same key in all records in the model
	directive('unique', function () { 
		return {
			require: 'ngModel',
			link: function(scope, elem, attr, ngModel) {
				
				// determine model and key to insist is unique within it
				var kp = attr.ngModel.split(".");
				var model = kp[0]+'s';
				var uKey = kp[1];
				
				// bind function to keyup
				elem.bind('keyup', function() {
				
					var testArr = new Array();

					scope[model].forEach(function(row, i) {
						console.log(testArr.indexOf(row[uKey]));
						if (testArr.indexOf(row[uKey]) > -1) {
							elem.parent().parent().addClass('error');
						} else {
							elem.parent().parent().removeClass('error');
						}
						testArr.push(row[uKey]);
					});
				
				});
				
			}
		};
	})

;