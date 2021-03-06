<?php
	require('partsTech.php');
	require('translate.php');
	$request = file_get_contents("php://input");
	$requestJson = json_decode($request, true);

	$intentDisplayName = $requestJson['queryResult']['intent']['displayName'];
	$params = $requestJson['queryResult']['parameters'];
	$outputContexts = $requestJson['queryResult']['outputContexts'];
	$partsTech = new PartsTech();
	switch ($intentDisplayName) {
		case 'partImage':
		
			$moreInfoContext = $outputContexts[0];
			foreach ($outputContexts as $key => $oc) {
				if(strpos($oc['name'], 'contexts/moreinfo') !== false) {
					$moreInfoContext = $oc;
					$ocid = $key;
					break;
				}
			}
			$images = $moreInfoContext['parameters']['images'];
			$text = array();
			$partNum = $params['part_number'];
			if($images[$partNum] != ''){
				$texts[] = array (
					'card' =>
					array (
						'title' => '',
						'subtitle' => '',
						'imageUri' => $images[$partNum],
					),
				);
			}else{
				$texts[] = ['text' => ['text' => ["No contamos con ninguna imagen para ese numero de parte"]]];
			}
			$fulfillment = array(
				"fulfillmentMessages" => $texts
			);
			echo json_encode($fulfillment);
			die();
		break;
		case 'search_part_number':
			$stores = [
				['id' => 149918,"name" => "Avenida Felipe Ángeles No. 333-A, Col. Progreso, Guadalajara, JA 44730, MX", "supplierName" => "NAPA Auto Parts" ],
				['id' => 149914,"name" => "CALZ. DEL EJERCITO #1396, COL. QUINTA VELARDE, Guadalajara, JA 44430, MX", "supplierName" => "AutoZone" ],
				['id' => 149919,"name" => "Av. Revolución #705, Col. General Real, Guadalajara, JA 44890, MX", "supplierName" => "WORLDPAC" ]
			];

			$searchParams = [	"partNumber" => [$params['part_number']]];
			$responseMsg = [];
			$responseMsg['pre'] = "Ahorita tenemos disponibles siguientes piezas disponibles en estas tiendas: \n";
			$foundPart = false;
			$responseMsg['store'] =[];
			$partImages = [];
			foreach ($stores as $store) {
				$storeId = $store['id'];
				$parts = $partsTech->requestQuote($searchParams, $storeId)['parts'];
				if(sizeof($parts) > 0) {
					$storeData['parts'] = [];
					$responseMsg['store'][] = "En la tienda de " . $store['supplierName'] ." que esta en ". $store['name']." tienen : \n";
					foreach ($parts as $part) {
						$partName = $part['partName'];
						$quantity = $part['quantity'];
						if($quantity == 0) {
							$quantity = $part['availability'][0]['quantity'];
						}

						if($part['quantity'] > 0) {
							$partName = translate($partName, 'es', 'en');
							$partImages[$part['partNumber']] = sizeof($part['images']) > 1 ? $part['images'][0]['preview'] : '';
							$responseMsg['store'][] =  $quantity.' '.$partName.' con precio de $'. $part['price']['cost']." con el numero de parte ". $part['partNumber']."\n";
							$foundPart = true;
							if(sizeof($responseMsg['store']) > 9) {
								break;
							}
						}
					}

				}
				if($foundPart) {
					break;
				}
				$responseMsg['store'] =[];
			}

			if(sizeof($responseMsg['store']) == 0) {
					$response = "Lo siento, pero el producto con ese numero de parte no esta disponible por el momento";
					$fulfillment = array(
						"fulfillmentText" => $response
					);
			} else {
				$texts = [];
				foreach ($responseMsg['store'] as $text) {
					$texts[] = ['text' => ['text' => [$text]]];
				}

				$outputContexts[] =	array(
					"name" => $requestJson["session"]."/contexts/moreInfo",
					"lifespanCount" => 5,
					"parameters"=> array(
						"images" => $partImages,
					)
				);
				$fulfillment = array(
					"fulfillmentMessages" => $texts,
					"outputContexts" => $outputContexts
				);
			}

			echo json_encode($fulfillment);
			break;
		case 'engine':
			if(!$params['engine']) {
				$response = "No me mandaste ningun motor, ¿Cual motor tiene tu auto?";
				die();
			}
			else {
				$outputContext = $outputContexts[1];
				foreach ($outputContexts as $oc) {
					if(strpos($oc['name'], 'contexts/part_search') !== false) {
						$outputContext = $oc;
						break;
					}
				}
				$partName = $outputContext['parameters']['partName'];
				$solicitedYear = $outputContext['parameters']['year'];
				$solicitedMakeId  = $outputContext['parameters']['makeId'];
				$solicitedModelId  = $outputContext['parameters']['modelId'];
				$solicitedSubmodelId = $outputContext['parameters']['submodelId'];
				$solicitedEngine = $params['engine'];
				$engines = $partsTech->getEngines($solicitedYear, $solicitedMakeId, $solicitedModelId, $solicitedSubmodelId);
				foreach ($engines as $engine) {
					$engineName = join(' ', explode('  ',$engine["engineName"]));
					$solicitedEngine = join(' ', explode('  ',$solicitedEngine));
					if (strpos($engineName, $solicitedEngine) !== false) {
						$engineId  = $engine['engineId'];
						$engineParams = $engine['engineParams'];
						break;
					}
				}
				if (empty($engineId)) {
					$response = 'No encontre un motor con ese nombre, ¿Seguro que lo escribiste bien? Los motores disponibles para tu carro son: ';
					foreach ($engines as $key => $engine) {
						if ($key < (count($engines)-1)) {
							$response .= $engine['engineName'].', ';
						}
						else {
							$response .= 'o '.$engine['engineName'];
						}
					}
				}
				else {
					$stores = [
						['id' => 149918,"name" => "Avenida Felipe Ángeles No. 333-A, Col. Progreso, Guadalajara, JA 44730, MX", "supplierName" => "NAPA Auto Parts" ],
						['id' => 149914,"name" => "CALZ. DEL EJERCITO #1396, COL. QUINTA VELARDE, Guadalajara, JA 44430, MX", "supplierName" => "AutoZone" ],
						['id' => 149919,"name" => "Av. Revolución #705, Col. General Real, Guadalajara, JA 44890, MX", "supplierName" => "WORLDPAC" ]
					];
					$partName = translate($partName, 'en', 'es');
					$searchParams = [
						'vehicleParams' => [
							"yearId" => intval($solicitedYear),
							"makeId"=> intval($solicitedMakeId),
							"modelId"=> intval($solicitedModelId),
							"subModelId"=> intval($solicitedSubmodelId),
							"engineId"=> $engineId,
							"engineParams" => $engineParams
						],
						'keyword' => $partName
					];

					$responseMsg = [];
					$responseMsg['pre'] = "Ahorita tenemos disponibles siguientes piezas disponibles en estas tiendas: \n";
					$foundPart = false;
					$responseMsg['store'] =[];
					$images = array();
					foreach ($stores as $store) {
						$storeId = $store['id'];
						$parts = $partsTech->requestQuote($searchParams, $storeId)['parts'];
						$imagen = array();
						if(sizeof($parts) > 0) {
							$storeData['parts'] = [];
							$responseMsg['store'][] = "En la tienda de " . $store['supplierName'] ." que esta en ". $store['name']." tienen : \n";
							foreach ($parts as $part) {
								$partName = $part['partName'];
								$quantity = $part['quantity'];
								$partNum = $part['partNumber'];
								$images[$partNum] = sizeof($part['images']) > 0 ? $part['images'][0]['preview'] : '';
								if($quantity == 0) {
									$quantity = $part['availability'][0]['quantity'];
								}

								if($part['quantity'] > 0) {
									$partName = translate($partName, 'es', 'en');
									$responseMsg['store'][] =  $quantity.' '.$partName.' con precio de $'.  $part['price']['cost']." con el numero de parte ". $part['partNumber']."\n";
									$foundPart = true;
									if(sizeof($responseMsg['store']) > 9) {
										break;
									}
								}
							}
						}
						if($foundPart) {
							break;
						}
						$responseMsg['store'] =[];
					}

					if(sizeof($responseMsg['store']) == 0) {
							$response = "Lo siento, pero el producto con ese numero de parte no esta disponible por el momento";
					} else {
						$texts = [];
						foreach ($responseMsg['store'] as $text) {
							$texts[] = ['text' => ['text' => [$text]]];
						}
						$outputContexts[] =	array(
							"name" => $requestJson["session"]."/contexts/moreInfo",
							"lifespanCount" => 5,
							"parameters"=> array(
								"images" => $images,
							)
						);
						$fulfillment = array(
							"fulfillmentMessages" => $texts,
						);
						echo json_encode($fulfillment);
						die();
					}
				}
			}
			$fulfillment = array(
				"fulfillmentText" => $response
			);
			echo json_encode($fulfillment);
 			break;
		case 'submodel':
			if(empty($params['submodel'])) {
				$response = "No me mandaste ninguna version, ¿Cual es la version de tu carro?";
			}
			else {
				$outputContext = $outputContexts[1];
				foreach ($outputContexts as $key => $oc) {
					if(strpos($oc['name'], 'contexts/part_search') !== false) {
						$outputContext = $oc;
						$ocid = $key;
						break;
					}
				}
				$solicitedYear = $outputContext['parameters']['year'];
				$solicitedMakeId  = $outputContext['parameters']['makeId'];
				$solicitedModelId  = $outputContext['parameters']['modelId'];
				$solicitedSubmodel = $outputContext['parameters']['submodel'];
				$submodels = $partsTech->getSubModels($solicitedYear, $solicitedMakeId, $solicitedModelId, "");
				if (count($submodels) < 2) {
					$outputContexts[] =	array(
						"name" => $requestJson["session"]."/contexts/engineSelection",
						"lifespanCount" => 5,
						"parameters"=> array(
							"submodelId" => $subModels[0]['submodelId'],
						)
					);
					$response = "¿Cual es el motor que necesita?";
					$outputContexts[$ocid] = $outputContext;
					$fulfillment = array(
						"fulfillmentText" => $response,
						"outputContexts" => $outputContexts,
					);
					echo(json_encode($fulfillment));
					die;
				}
				foreach ($submodels as $submodel) {
					$submodelName = $submodel["submodelName"];
					if (strtolower($solicitedSubmodel) == strtolower($submodelName)) {
						$submodelId  = $submodel['submodelId'];
						$outputContext['parameters']["submodelId"] = $submodelId;
					}
				}
				if (empty($submodelId)) {
					$response = 'No encontre una version de tu carro con ese nombre, ¿Seguro que lo escribiste bien? Las versiones de tu carro son: ';
					$buttons = [];
					foreach ($submodels as $key => $submodel) {
						if ($key < (count($submodels)-1)) {
							$response .= $submodel['submodelName'].', ';
						}
						else {
							$response .= 'o '.$submodel['submodelName'];
						}
					}
				}
				else {
					$availableEngines = $partsTech->getEngines($solicitedYear, $solicitedMakeId, $solicitedModelId, $submodelId);
					$response = 'Que motor tiene tu carro: ';
					$buttons = [];
					foreach ($availableEngines as $key => $engine) {
						if ($key < (count($availableEngines)-1)) {
							$response .= $engine['engineName'].', ';
						}
						else {
							$response .= 'o '.$engine['engineName'];
						}
						array_push($buttons, $engine['engineName']);
					}
				}
				$response .= '?';
				$outputContexts[$ocid] = $outputContext;
				$fulfillment =
				array (
					'fulfillmentMessages' =>
					array (
					  0 =>
					  array (
						'text' =>
						array (
						  'text' =>
						  array (
							0 => $response
						  ),
						),
					  ),
					  1 =>
					  array (
						'quickReplies' =>
						array (
						  'quickReplies' =>
							$buttons
						),
					  ),
					),
				"outputContexts" => $outputContexts,
			);
			echo(json_encode($fulfillment));
			die();
			}
			$outputContexts[$ocid] = $outputContext;
			$fulfillment = array(
				"fulfillmentText" => $response,
				"outputContexts" => $outputContexts,
			);
			echo(json_encode($fulfillment));
			break;
		case 'SearchPartName':
			$outputContext = $outputContexts[1];
			$ocid = -1;
			foreach ($outputContexts as $key => $oc) {
				if(strpos($oc['name'], 'contexts/part_search') !== false) {
					$outputContext = $oc;
					$ocid = $key;
					break;
				}
			}
			$year = $params['year'];
			$makeName = $params['make'];
			$modelName = $params['model'];
			$makeId = 0;
			$allMakes = $partsTech->getMakes($year, '', '');
			foreach($allMakes as $make){
				if($make["makeName"] == $makeName){
					$makeId = $make["makeId"];
					$outputContext['parameters']["makeId"] = $makeId;
					break;
				}
			}
			if($makeId == 0){
				$fulfillment = array(
					"fulfillmentText" => "No encontre la marca ".$makeName.", ¿Estas seguro que lo escribiste bien?"
				);
				echo(json_encode($fulfillment));
				die;
			}

			$models = $partsTech->getModels($year, $makeId, '');
			$modelId = 0;
			foreach($models as $model){
				if($model["modelName"] == $modelName){
					$modelId = $model["modelId"];
					$outputContext['parameters']['modelId'] = $modelId;
					break;
				}
			}
			if($modelId == 0){
				$fulfillment = array(
					"fulfillmentText" => "No encontre el modelo ".$modelName.", ¿Estas seguro que lo escribiste bien?"
				);
				echo(json_encode($fulfillment));
				die;
			}
			$subModels = $partsTech->getSubModels($year, $makeId, $modelId, '');
			if(sizeof($subModels) < 2){
				$outputContexts[] = array(
										"name" => $requestJson["session"]."/contexts/engineSelection",
										"lifespanCount" => 5,
										"parameters"=> array(
											"submodelId" => $subModels[0]['submodelId'],
										)
									);
				$outputContext['parameters']["submodelId"] = $subModels[0]['submodelId'];
				$availableEngines = $partsTech->getEngines($year, $makeId, $modelId, $subModels[0]['submodelId']);
				$response = 'Que motor tiene tu carro: ';
				foreach ($availableEngines as $key => $engine) {
					if ($key < (count($availableEngines)-1)) {
						$response .= $engine['engineName'].', ';
					}
					else {
						$response .= 'o '.$engine['engineName'];
					}
					$buttons[] = $engine['engineName'];
				}
				$response .= '?';
				$outputContexts[$ocid] = $outputContext;
				$fulfillment =
				array (
					'fulfillmentMessages' =>
					array (
					  0 =>
					  array (
						'text' =>
						array (
						  'text' =>
						  array (
							0 => $response
						  ),
						),
					  ),
					  1 =>
					  array (
						'quickReplies' =>
						array (
						  'quickReplies' =>
							$buttons
						),
					  ),
					),
				"outputContexts" => $outputContexts,
			);
				echo(json_encode($fulfillment));
				die;
			}
			$response = "¿De cual versión es: ";

			foreach($subModels as $index=>$subModel){
				$response .= $subModel["submodelName"];
				if($index == sizeof($subModels) - 2){
					$response .= " o ";
				}else{
					if($index < sizeof($subModels) - 1){
						$response .= ", ";
					}
				}
			}
			$response .= "?";
			$outputContexts[$ocid] = $outputContext;
			$fulfillment = array(
				"fulfillmentText" => $response,
				"outputContexts" => $outputContexts,
			);
			echo(json_encode($fulfillment));
			die;

		default:
			# code...
			break;
	}
?>
