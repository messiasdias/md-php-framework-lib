<?php
/**
 * Maker Class
 */

namespace App\Maker;
use App\App;
use App\Database\DB;
use App\Database\Table;



class Maker
{
	private $app, $path, $response, $migrations=[], $seeds=[], $tables=null;

	public function __construct(App $app ) {
		$this->app = $app;
		$this->path = $app->path;
	}	


	private function get_classes(string $type,string $class_name=null){

		$path =''; $return=[]; $action ='';
		$class_name = is_null($class_name) ? 'all' : $class_name;

		switch (strtolower($type) ) {
			case 'migrations': 
				$action ='Migration';
				$path = $this->path.'src/Database/'.ucfirst($type).'/';
			break;
			case 'seeds':
				$action ='Seeder';
				$path = $this->path.'src/Database/'.ucfirst($type).'/';
			break;

			case 'tables':
				$action ='Migration';
				$classes = $this->get_classes('Migration', $class_name);

				  if ( $classes ) {	
					foreach ($classes as $key => $class) {
							$table =  strtolower(str_replace('.php' ,'' ,explode('\\', $class)[ count(explode('\\', $class))-1 ])) ;
							$regex = '/^([a-zA-Z0-9\\]{0,}[\\'.$table.']{1,})$/';

							if ( ( ($class_name == 'all') | @preg_match( $regex , $table ) )   && ($table != 'Migration' ) ){
								array_push($return, strtolower(str_replace(['Migration','src/' ],'',$table) ) );
							}
						}
					}
						
						if ($return) {
							return $return;
						}else{
							return false;
						}

			break;		
			
			default:
				return false;
			break;
		}


		foreach (glob($path.'*.php') as $key => $value)
		{	
			$value = str_replace([$this->path,'/'], ['App/' ,'\\'],str_replace(['src/' ,'.php'], '', $value));
			$value_exp = str_replace( $action,'', explode('\\', $value)[count( explode('\\', $value) )-1] );

			if(  ( ($class_name == 'all') && ( $value_exp != 'Migration' ) ) | (  strtolower($value_exp ) == strtolower( $class_name )  ) ){
				 array_push($return, $value);
			}
		}

		if ($return) {
			return $return;
		}

		return false;
	}






	public function migrate($command){
		
		$response = '<center style="padding:50px;">'; 

		if ( !is_null($command) && ( count( explode( ':',$command) ) > 1 ) ) {

			$command_exp = explode( ':',$command);

			switch ($command_exp[0]) {
				case 'create':
				case 'drop':
			
					$response .= '<h3 style="color: #3333FF;">Running '.ucfirst($command_exp[0]).' Tables!</h3>';
					$response .= $this->migrator( strtolower($command_exp[0]) ,$this->get_classes('Migrations', $command_exp[1]));
					
					break;
				case 'reset':

					$response .= '<h3 style="color: #3333FF;">Running '.ucfirst($command_exp[0]).' Tables!</h3>';
					$response .= $this->migrator('drop',$this->get_classes('Migrations', $command_exp[1]));
					$response .= $this->migrator('create',$this->get_classes('Migrations', $command_exp[1]));

					break;	

				case 'seed':

					$response .= '<h3 style="color: #3333FF;">Running '.ucfirst($command_exp[0]).' Tables!</h3>';

					$classes = $this->get_classes('Seeds', $command_exp[1]);
	
					if ($classes ) {

					  if ( $command_exp[1] == 'all') {	
					   foreach ($classes as $key => $class) {
					   		$class_exp = str_replace('Seeder',null,explode('\\', $class)[count(explode('\\', $class))-1]);
					
					   		if ($class ) {
					   			$response .= $this->seed( $class, maker_args[strtolower($class_exp)] );
					   		}

					   	}	

					   }elseif( isset(maker_args[$command_exp[1]]) ) {
					   		$response .= $this->seed( $classes[0], maker_args[$command_exp[1]] );
					   }
						
					}else{
						
						$response .= '<p style="color: brown;" >The <b>'.ucfirst($command_exp[1]).
						'</b> Seeder Class  does not exist in database!</p>';
					}

				break;

				case 'spoon' :
					 $response .= '<h3 style="color: #3333FF;">Running Spoon Tables!</h3>';
					 
					 $classes = $this->get_classes('tables', $command_exp[1] );	
					 if ($classes) {
						 foreach ($classes as $table) {
							$response .= $this->spoon($table);	
						 } 
					 }else{
						 $response .= '<p style="color: brown;" >The <b>'.ucfirst($command_exp[1]).
						 '</b> table does not exist in database!</p>';
					 }	

				break;			
				
				default:

					$response .= '<h3 style="color: #3333FF;">Help</h3>';
					$response .= '<p style="color:brown;">'
					.'Usage:  /maker/migrate/create|drop|reset|seed[:table_name|all]</p>';

				 break;
			}

			

		}
		else{

			$response .= '<h3 style="color: #3333FF;">Help</h3>';
			$response .= '<p style="color:brown;"><br>
	   			Usage:  /maker/migrate/create|drop|reset|seed[:table_name|all]
	   			</p>';
		
		}
		
		return $response.'</center>';


   }





	private function migrator($type, $classes){ 

	 $msg=[]; $rs=null; 	$response='';

	 if($classes) {	

	   foreach ($classes as $key => $class) {
		  $table_name = strtolower( str_replace( 'Migration', '', array_slice(explode('\\', $class) , -1 )[0]  ) );
		  $table = new Table($table_name);

			switch (strtolower($type)) {
				case 'create':
			
					    if ( !$table->exists() ) {
							unset($table);
							$migration = class_exists($class) ?  new $class() : false;

							if ($migration) {

								if($migration->create()){
									$msg = 1;
								}else{
									$msg = 4;
								}

								
								break;

							}else{
								$msg = 4;
							} 

						}else{
							$msg = 2;
						}
					
					break;

				case 'drop':
						
						if ($table->exists()) {
								$rs = $table->drop(); 

								if ($rs){
									$msg =1;
								}else{	
									$msg = 4;
								}		
								break;

						}else{
							$msg = 3;
						}

				 break;
								
				default:
					$msg = 5;
				break;
			}

		
			switch ($msg) {

				case 1:
					$response .= '<p style="color:green;">'.
					'Table <b>'.strtolower($table_name).'</b> '.strtolower($type).' Successfully!</p>';
				break;


				case 2:
					 $response .= '<p style="color:brown;">'
					 .'The <b>'.strtolower($table_name).'</b> Table already exists in the Database!</p>';
				break;

				case 3:
					 $response .= '<p style="color:brown;">'.
					 'The Table <b>'.strtolower($table_name).'</b> no exists in the Database or Class Table no is defined in App/Database/'. str_replace(' ' , '_', ucwords( str_replace('_' , ' ', strtolower($table_name) ) ) ) .'Migration.php !</p>';
				break;

				case 4:
					$response .= '<p style="color:red;">'.
					'An error occurred while '.strtolower($type).' table <b>'.strtolower($table_name).'</b>!</p>';	
				break;

				case 5:
					$response .= '<p style="color:brown;"><br>'
					.'Usage Command: /maker/migrate/[create|drop|reset|seed|spoon:[table_name|all]
	   			</p>';
				break;
				
			}

		}

	}
	else{

		$response .= '<p style="color:brown;"><br> The Table no exists in the Database! </p>';
	}

	
	return $response; 
	
	}







	public function seed($classes, $args= null){

		$class_obj=null; $response=''; $name='';

		if ($classes) {

			if ( is_array($classes)  ) {

				foreach ($classes as $class) {
					 $name = explode('\\', $class)[ count(explode('\\', $class))-1 ];
					 $class_obj = class_exists($class) ? new $class($args): false ;
					 $response .= $class_obj->get_response();
				}

			}else{
				$name = explode('\\', $classes)[ count(explode('\\', $classes))-1 ];
				$class_obj = class_exists($classes) ? new $classes($args): false ;
				$response .= $class_obj->get_response();
			}	


				return $response;

		}

		$response .= '<p style="color: brown;" >The <b>'.$name.'</b> class does not exist in database!</p>';
	


		return $response;

	}






	public function spoon($tables_spoon){
	
		$tables=[]; $response = '';
		if (!is_array($tables_spoon)){
			$tables[0] = $tables_spoon;
		}	

		foreach ($tables as $table) {

					$db = new DB();
				 	$args = null;  $data_array=[];
					$data = $db->select($table, '*');
				
				 	if ( is_object($data)){
				 		$data_array[0] = $data;
				 	}else{
				 		$data_array = $data;
					} 
					 

				 	if ($data_array) {

				 		switch ($table) {

				 			case 'jobs':
				 			case 'demos':		
				 	
				 				if ($data_array) {
				 					
						 			foreach ($data_array as $key => $value) {
						 				if ( strrpos(strtolower($value->title),'teste') | strrpos(strtolower($value->description), 'teste') ){
						 					$rs = $db->delete($table, ['id'=> $value->id]);
						 					if( $rs->status) {
												 $response .=  '<br><p style="color:green;">Deleting '
												 .ucfirst($table)." ".$value->title."</b>";
						 					}else{

						 						if ($rs->errors) {
										    		foreach ($rs->errors as $key => $value) 
										    		{
										    			$response .= '<p  style="color:brown;">Error: '.$key.' | '.$value.'</p>' ;
										    		}
										    	}	
						 					}


										}
										else{
											$response .=  '<br><p style="color:0000FF;">The '.ucfirst($table)
													." ".$value->title." n is Test Item! </b>";
										}	
						 			}

						 			
					 			}else{
									 $response .=  '<br><p style="color:brown;">No Found Resgisters of Test in table '.
									 $table.'!</b>';
						 		}
						 			
				 				break;
				 			

							case 'users':
		
								if ($data_array) {	
									foreach ($data_array as $key => $value) {

												if ( strrpos(strtolower($value->last_name),'teste') | strrpos(strtolower($value->email), 'teste') ){
													$rs = $db->delete($table, ['id'=> $value->id]);
													if($rs) {
														$response .=  '<br><p style="color:green;">Deleting '
														.ucfirst($table)." ".$value->first_name."!</b>";
													}
												}else{
													$response .=  '<br><p style="color:0000FF;">The user '.ucfirst($table)
													." ".$value->first_name." n is Test Item! </b>";
												}

									}	
								}
								else {
									$response .=  '<br><p style="color:brown;">No Found Resgisters of Test in table '
																.$table.'! </b>';
								}
						 				

							 break;
							 
				 		} //end switch

				 	 }else{
							$response .=  '<br><p style="color:brown;">No Found table '
							.$table.' in database, or this is empty! </b>';
				 	 }
				 	 	

				 }

				 exit;

				 

		return $response;	
	}
	



	public function file($data,$replace=[[],[]]){

			$usage = '<p style="color:brown;"><br>'
			.'Usage Command: /maker/file/[controller|model|seeder|migration]:[class_name]|route:[app|api:file_name ]|'.
			'config:[middlewares|db|key|app]</b><br>';

			$rs=null; $response = ''; $continue = false; $explode = explode( ':', $data); 
			$command = isset($explode[0]) ? $explode[0] : false ;
			$subcommand= isset($explode[1]) ? $explode[1] : false ;
			$subcommand2= isset($explode[2]) ? $explode[2] : false ;
			$type_exists = function($this_command ,  $this_subcommand , $this_makefile ){	
				return  isset( $this_makefile->templates->$this_command->type ) && ( $this_subcommand && isset( $this_makefile->templates->$this_command->type->$this_subcommand )) ;
			};

			$response .= '<h3>Running Make File '.ucfirst($explode[0]).'!</h3>';	

			$templates_path =  $this->app->vendor_path.'Maker/templates/';
			$makefile = json_decode(file_get_contents($this->app->vendor_path.'Maker/maker.json') );
			$template = $templates_path;
			$filename = $this->app->path;
			
			if( $command && isset( $makefile->templates->$command )  ){

				if( $subcommand  ){

					foreach( array('template', 'filename' ) as $item ) {

						$subitem = '';
							switch ($item) {
								case 'template':
									$subitem = 'src';
								break;

								case 'filename':
									$subitem = 'path';
								break;
			
							}

							if( $type_exists($command, $subcommand, $makefile) && isset(  $makefile->templates->$command->type->$subcommand->$subitem )  ) {
								$$item .= $makefile->templates->$command->type->$subcommand->$subitem;
							}else{
								if(isset( $makefile->templates->$command->$subitem  )){
										$$item .= $makefile->templates->$command->$subitem;
								}else{
									$$item = false;
								}
							}
						

					}

					if( $filename && $template ){
						$cocat_name = '';		
							
						if ( $subcommand2 && in_array($command, array('route','config' )) ){
							$cocat_name = $subcommand2;
						}elseif( !isset($makefile->templates->$command->type) ){
							$cocat_name = $subcommand;
						}elseif( isset($makefile->templates->$command->type->$subcommand ) ) {
							$cocat_name = $subcommand;
						}else{
							$filename = false;
						}

						if( $filename){
							$filename .=  str_replace('_' , '', !in_array($command, array('route','config' ) ) ? ucwords($cocat_name , '_') : strtolower($cocat_name) )   .'.php';
							$rs =  $this->makefile($filename ,$template, $replace);
						}
						else{
							$rs = [false, "Filename not isset!" ];
						}			
						

					}else{
						$rs = [false, "Template for $command <b>'$subcommand'</b> not fount !" ];
					}

					

					if ( $rs[0] ) {
						$response .= '<br> <p style="color:green;">'.$rs[1].'</b>';
					}else{
						$response .=  '<br><p style="color:brown;">'.$rs[1].'</b>';
					}


				}else{
					$response .= $usage;
				}	
			
			}else{
				$response .= $usage;
			}
			
		return $response;	
	}


	private function makefile(string $filename, string $template , $replace=[[],[]] ) {

		if ( file_exists( $filename )  ) {
			return [false, 'The file <b>"'.$filename.'"</b> already exists!'];
		}else{
			if( is_writable(dirname($filename)) && $template ){	
				$tmp = fopen($template, 'r');
				$content = fread($tmp, filesize($template));
				$document = fopen($filename, 'a+');
				$rw = fwrite($document, '<?php ');
				$rw = fwrite($document , str_replace( array_merge( ['{{name}}'], $replace[0] ) , array_merge( [ ucwords( basename($filename, '.php' ), '_' ) ], $replace[1] )  ,$content) );
				fclose($document);	

				$chmod = chmod($filename,0775); 

				if (file_exists($filename)) {
					return [true, "Creating <b>$filename</b> Successfully!" ];
				}

				return [false, "An error occurred while creating file  <b>$filename</b> !" ];
			}else{
				return [false, "Permission denied for create file <b>$filename</b>! <br><br> ".
			           'Execute on cli in the root directory: <br>$ <i>sudo chown user:root -R</i> .
					   '];
			} 

		}

	}	


	public function show(String $subcommand){

		switch($subcommand){
			case 'seeds':
			case 'migrations':
				$type = ucfirst($subcommand);
			break;

			case 'tables':
				$type = ucfirst('migrations');
			break;

			default:
				$type = false;
			break;

		}

		$response = '<h3  style="color: #3333FF;" >Running List '.ucfirst($subcommand).'!</h3>';	
		$response .=  '<ul>';

		if ( $type != false ) {
			foreach( $this->get_classes($type, 'all') as $class ){
				if( $type == 'Migrations') {
				 $obj = new $class();
				 $response .=  '<li style="color:'.(  $obj->exists() ? 'green' : 'brown' ).';" >';
				}else{
					$response .=  '<li style="color:green;" >';
				}

				$response .=  explode('\\', $class )[ count(explode('\\', $class )) -1];
				$response .=  '</li>';
			}
		}else{
			$this->app->redirect('/maker');
		}
		
		$response .=  '</ul>  ';
		return $response;

	}

	








}
