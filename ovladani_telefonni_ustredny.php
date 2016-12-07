<?php

/***
   *  Jedna se o ukazku "šílené" action a render funkce ridici vzdalenou telefonni ustrednu podle toho zda a kolik ma volajici uzivatel kreditu.
   *  Posledni funkce TelfaTerminator se stara o preruseni hovoru ve chvili kdy klient kredit vycerpa.
   *  Citlive udaje jsem z kodu odstranil, neb co vidite je primo v provozu 
   *
   ***/



	public function actionTelfaHandler(){

      $body = @file_get_contents('php://input');
   	$json = json_decode($body);

   	$caller_number = $json -> caller_number;
   	$called_number = $json -> called_number;
   	if(strpos($body,'"call_result":null')!=0){
         $bridge_result='null';
   	}else{
      	$bridge_result = $json -> bridge_result;
   	}
   	$hangup = $json -> hangup;
   	$pokracovani = $json -> pokracovani;
      $choise = $json -> received_digits;
      $status = " || Status: prichozi volani<br />";

		$kreditni = new kreditniModel();
      $allow = $kreditni->verifyUserCall($caller_number);

            
   	if( !$bridge_result ){  // pokud existuje bridge_result, hovor NEZACAL anebo BYL ukoncen. Je treba prehrat hlasku a ulozit data hovoru
      	if( !$hangup ){  // pokud exituje hangup, hovor probehl a prave skoncil, je treba ulozit data hovoru
            if($allow==1){  // pokud ma uzivatel dostatecny kredit
                  if( $choise > 0 ){ // pokud uzivatel jiz neco navolil
                     if( strlen($choise) < 2 ) { // pokud uzivatel navolil pouze jednu cislici (linky jsou dvojmistne)
                  	    $a = array( 'action' => 'play', 'recording_id' => '1424' ); // hlaseni: litujeme volba je neplatna
                              // zapis do logu
                              $status = " || Status: neplatna volba<br />";
                              $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                              $log = array(
                           		 'cl_type'=>27,
                           		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                           		 'gid'=>$usr->gid,
                           		 'cl_info'=>'Neplatná volba - ' . $choise,
                              );
                              $kreditni->newLog($log);
                              
                     }else{ // pokud uzivatel navolil dvojcifernou linku
                        $operid = $kreditni->giveMeOnlineOperByLinka($choise); // zkusime ji vyhledat
                        if(!$operid){ // pokud neexistuje
                  	    $a = array( 'action' => 'play', 'recording_id' => '1424' ); // hlaseni: litujeme volba je neplatna
                              // zapis do logu
                              $status = " || Status: neplatna volba<br />";
                              $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                              $log = array(
                           		 'cl_type'=>27,
                           		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                           		 'gid'=>$usr->gid,
                           		 'cl_info'=>'Neplatná volba - ' . $choise,
                              );
                              $kreditni->newLog($log);
                        }else{
                           
                              if($choise=='99' OR $choise=='33'){ // specialni rizeni linek 33 a 99 - volani na skupinu
                           
                                 $groupid = 203;
                                 switch($choise){
                                    case 99: $groupid=203; break;
                                    case 33: $groupid=221; break;
                                    default: $groupid=203; break;
                                 }
                                 // zapis do logu
                                 $a = array( 'action' => 'transfer_to_group', 'call_group_id' => $groupid, 'group_transfer_recording_id' => '1414', 'group_mobile_present' => 'true', 'group_continue_busy' => 'true', 'skip_caller_id_transfer' => 'true'  );
                                 $status = " || Status: zahájení vyzvánění skupině (linka ".$choise.")<br />";
                                 $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                                 $log = array(
                              		 'cl_type'=>22,
                              		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                              		 'gid'=>$usr->gid,
                              		 'cl_info'=>'Vyzvánění skupině - linka ' . $choise,
                              		 'cl_link'=> $choise,
                                 );
                                 $kreditni->newLog($log);
                              }else{
                              
                           // 
                           // VOLANI KONKRETNIMU OPERATOROVI
                           //
                                 if($operid['status']==1){ // zkontrolujeme zda je operator stale prihlasen, pokud neni
                           	    $a = array( 'action' => 'play', 'recording_id' => '1432' ); // hlaseni: odhlasena  oper
                                       //zapis do logu
                                       $status = " || Status: odhlasena operatorka<br />";
                                       $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                                       $log = array(
                                    		 'cl_type'=>27,
                                    		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                                    		 'gid'=>$usr->gid,
                                    		 'cl_info'=>'Neprihlasena operatorka - ' . $choise,
                                       );
                                       $kreditni->newLog($log);
                                 }else{ // pokud je operator prihlasen, zahajime vyzvaneni 
                                       $oper = $operid['telfa_id'];
                                       $a = array( 'action' => 'transfer_to_group', 'call_group_id' => $oper, 'group_transfer_recording_id' => '1414', 'group_mobile_present' => 'true', 'group_continue_busy' => 'true', 'skip_caller_id_transfer' => 'true'  );
                                       // zapis do logu
                                       $status = " || Status: zahájení vyzvánění operátorce (linka ".$choise.")<br />";
                                       $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                                       $log = array(
                                    		 'cl_type'=>22,
                                    		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                                    		 'gid'=>$usr->gid,
                                    		 'cl_info'=>'Vyzvánění operátorce - linka ' . $choise,
                                    		 'cl_link'=> $choise,
                                       );
                                       $kreditni->newLog($log);
                                 }
                              }
                        }
                     }
                  
                  // POKUD UZIVATEL NIC NENAVOLIL opakuj hlavni menu
                  }else{  
                  	     if($pokracovani=='9'){
                           	  $a = array( 'action' => 'menu', 'recording_id' => '1413', 'menu_timeout' => '5', 'max' => '2' );
                                 // zapis do logu
                                 $status = " || Status: hlavni menu (case 0)<br />";
                                 $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                                 $log = array(
                              		 'cl_type'=>21,
                              		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                              		 'gid'=>$usr->gid,
                              		 'cl_info'=>'Zavolání / hlavní menu (IVR).',
                                 );
                                 $kreditni->newLog($log);
                  	     }else{
                     	     $a = array( 'action' => 'answer', 'pokracovani' => '9' );
                  	     }
                  }
            }else{  // uzivatel nebyl schvalen pro spojeni
                  if($allow==0){ // protoze neni registrovan (jeho telefonni cislo)
               	    $a = array( 'action' => 'play', 'recording_id' => '1851', 'pokracovani' => '9' );
                           // zapis do logu
                           $status = " || Status: neznamy volajici<br />";
                           $log = array(
                        		 'cl_type'=>31,
                        		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                        		 'gid'=>0,
                        		 'cl_info'=>'Neznámý volající anebo neregistrované číslo',
                           );
                           $kreditni->newLog($log);
                 }else if($allow==2){ // protoze ma mene nez 30 sekund na svem kreditu a nestihl by se spojit s operatorkou
               	    $a = array( 'action' => 'play', 'recording_id' => '1852', 'pokracovani' => '9' );
                           // zapis do logu
                           $status = " || Status: maly kredit uzivatele (mene nez 30 sekund)<br />";
                           $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                           $log = array(
                        		 'cl_type'=>32,
                        		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                        		 'gid'=>$usr->gid,
                        		 'cl_info'=>'Nespojen pro nízký kredit',
                           );
                           $kreditni->newLog($log);
                  }
            }

         // PRAVE SKONCENY HOVOR  (NIKOLIV DOKONČENÝ. tzn uzivatel mohl vybirat linky a netrefit se nikomu prihlasenemu a nasledne se rozhodnout hovor ukoncit aniz by se dovolal - nemusi byt co uctovat
      	} else {
      	   $a = array( 'action' => 'hangup' ); // odesleme potvrzeni do ustredny o skoncenem hovoru
      	   // zapiseme log
            $status = " || Status: konec hovoru<br />";
            $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
            if($allow!=1){
               $gid = 0;
            }else{
               $gid = $usr->gid;
            }
            $log = array(
         		 'cl_type'=>26,
         		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
         		 'gid'=>$gid,
         		 'cl_info'=>'Konec hovoru',
            );
            $kreditni->newLog($log);
      	}

      // DOKONCENY HOVOR - jiz je co uctovat
      } else {
         	  switch($bridge_result){ // Zapiseme udaje hovoru a log podle vraceneho vysledného hlaseni ustredny navracime rízeni zpet anebo ukoncujeme hovor
                  case 'SUCCESS':
                  case 'USER_NOT_REGISTERED':
               	  $a = array( 'action' => 'hangup' );
                     $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                     $datein = $kreditni->findCallStart($caller_number);
                     $dateout = date('Y-m-d H:i:s', time());
                     $status = " || Status: konec hovoru (" . $datein['credit'] . " sekund)<br />";
                     if($datein['result']==1){
                        $mystart=$datein['datetime'];
                        $mycredit=$datein['credit'];
                        $myoper=$datein['oper'];
                           $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                        $kreditni->doneCall($usr->gid,$mycredit,$caller_number,$mystart,$dateout,$myoper);
                           $log = array(
                        		 'cl_type'=>26,
                        		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                        		 'gid'=>$usr->gid,
                        		 'cl_info'=>'Konec hovoru / '.$datein['credit'].'s.',
                           );
                           $kreditni->newLog($log);
                     }else{
                        $status = " || Status: konec hovoru (HOVOR BEZ ZACATKU / TOTO BY SE NEMELO STAVAT!!)<br />"; // VAROVANI (ne vzdy me informuji o změnách v API ústředny)
                           $usr->gid = 1; 
                           $log = array(
                        		 'cl_type'=>26,
                        		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                        		 'gid'=>$usr->gid,
                        		 'cl_info'=>'Konec hovoru bez zacatku (volajici: '.$caller_number.')',
                           );
                           $kreditni->newLog($log);
                     }
                  break;
                  case 'USER_BUSY':
                           $a = array( 'action' => 'menu', 'recording_id' => '1422', 'menu_timeout' => '5', 'max' => '2' ); // kartarka hovori, menu 
                           $status = " || Status: pokus o volani obsazene operatorce<br />";
                           $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                           $log = array(
                        		 'cl_type'=>24,
                        		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                        		 'gid'=>$usr->gid,
                        		 'cl_info'=>'Operátorka obsazena',
                           );
                           $kreditni->newLog($log);
                  break;
                  case 'null':
                           $a = array( 'action' => 'menu', 'recording_id' => '1422', 'menu_timeout' => '5', 'max' => '2' ); // kartarka hovori, menu 
                           $status = " || Status: pokus o volani obsazene operatorce<br />";
                           $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                           $log = array(
                        		 'cl_type'=>24,
                        		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                        		 'gid'=>$usr->gid,
                        		 'cl_info'=>'Operátorka obsazena',
                           );
                           $kreditni->newLog($log);
                  break;
                  case 'NO_ANSWER':
                  	     $a = array( 'action' => 'menu', 'recording_id' => '1422', 'menu_timeout' => '5', 'max' => '2' ); // kartarka neni online, menu 
                          $status = " || Status: pokus o volani obsazene operatorce<br />";
                           $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                           $log = array(
                        		 'cl_type'=>25,
                        		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                        		 'gid'=>$usr->gid,
                        		 'cl_info'=>'Operátorka nezvedá',
                           );
                           $kreditni->newLog($log);
                  break;
                  default:
               	  $a = array( 'action' => 'hangup' );
                     $status = " || Status: konec hovoru z neznameho duvodu (bridge result: " . $bridge_result . ")<br />";
                     $usr = $kreditni->getCreditUserDataByPhone($caller_number); 
                     $log = array(
                  		 'cl_type'=>26,
                  		 'cl_datetime'=>date('Y-m-d H:i:s', time()),
                  		 'gid'=>$usr->gid,
                  		 'cl_info'=>'Konec hovoru (jiný důvod)',
                     );
                     $kreditni->newLog($log);
            	  break;
         	  }
      }


   	$itemjson = json_encode($a);
      	
   	$this->status = $itemjson;
   	
   	
   }
	public function renderTelfaHandler(){
      $this->template->js = $this->status;

   }
   
   //
   //
   //    TELFA TERMINATOR
   //
   //

   public function actionTelfaTerminator(){
      // nacteme model
      $kreditni = new kreditniModel();

      // ZJISTENI PROBIHAJICICH HOVORU
      $url="https://www4.telfa...xml";
   	$ch = curl_init($url);
      curl_setopt($ch, CURLOPT_TIMEOUT, 10);
   	curl_setopt($ch, CURLOPT_USERPWD, "martin@webest.cz:ef...");
   	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
   	$fin="";
   	if(!$info = curl_getinfo($ch)){
      	curl_close ($ch);
   	}else{ // pokud mame data
      	$xml = curl_exec($ch);
      	curl_close ($ch);
      	$clls = array();
      	$mycall = array();
      	$calls = array();
      	$i=0;
      	$go = 0;
            // pokud API ustredny nevraci mishmash
         if(strpos($xml,'<nil-classes')==false){
               $telfausers = new SimpleXMLElement($xml);
               foreach($telfausers as $usr){
                     $clls[(int)$usr->id] = $usr;
               }
             
            // PROJDEME PROBIHAJICI HOVORY (spravne hovory nacteme do pole calls[])
            foreach($clls as $xcall => $xvalue){
               $go = 0;
               foreach($xvalue as $call => $val){ // overime ze jde o volani na kreditni linku
                  if($call=='dialed_number' AND $val=='222...246') $go = 1;
                  if($call=='dialed_number' AND $val=='222...903') $go = 1;
                  if($call=='dialed_number' AND $val=='00421221...302') $go = 1;
                  if($call=='dialed_number' AND $val=='421221...302') $go = 1;
                  if($call=='call_id') $mycall['call_id'] = $val; // ulozime id hovoru
                  if($call=='caller_number') $mycall['caller_number'] = $val; // cislo volajiciho
                  if($call=='answered_at') $mycall['start'] = $val; // cas zacatku hovoru
                  $res .= $call . '  /  ';
               }
               if($go){
                  $calls[$i] = $mycall;
               }
               $i++;
            }
         }

   		
   		//
   		// checking call to beep
   		//
   		// zde overujeme zda je nektery volajici uzivatel minutu pred koncem sveho kreditu. Pokud ano, zapipame mu do hovoru
         $beeps = $kreditni->checkCallsBeeping($calls);  // kontrola hovoru k zapipani -> provadi se v modelu
         if(count($beeps)>0){  // PIPANI HOVORU
            foreach($beeps as $callid){
               $url="https://www4.telfa.../play_recording.xml";
            	$ch = curl_init();
            	$data = "
                  <play_recording>
                     <call_id>".$callid."</call_id>
                     <recording_id>2503</recording_id>
                     <side>both</side>
                   </play_recording>
                      ";
            	$ch = curl_init($url);
               curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            	curl_setopt($ch, CURLOPT_USERPWD, "martin@webest.cz:ef...");
            	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            	curl_setopt($ch, CURLOPT_POST,1);
            	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
            	curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
            	$info = curl_getinfo($ch); 
            	$fin = curl_exec($ch);
            	curl_close ($ch);             
            }
         }   		
   		
   		// checking call to terminate
   		//
   		// zde overujeme zda je nektery volajici uzivatel na konci sveho kreditu. Pokud ano, prerusime hovor a prehrajeme hlaseni
         $terminate = $kreditni->checkCallsTermination($calls);  // kontrola hovoru k ukonceni
         if(count($terminate)>0){  // UKONCOVANI HOVORU
            foreach($terminate as $callid){
               $url="https://www4.telfa....xml";
            	$ch = curl_init();
            	$data = "
                      <interrupt_call>
                        <id>".$callid."</id>
                        <recording_id>1869</recording_id>
                      </interrupt_call>
                      ";
            	$ch = curl_init($url);
               curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            	curl_setopt($ch, CURLOPT_USERPWD, "martin@webest.cz:ef...");
            	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            	curl_setopt($ch, CURLOPT_POST,1);
            	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
            	curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
            	$info = curl_getinfo($ch); 
            	$fin = curl_exec($ch);
            	curl_close ($ch);             

            }
         }         
      }

      $this->status = $fin;      
      
   }
   
   public function renderTelfaTerminator(){
      $this->template->xml = $this->status;
   }   
   
   
   
   
   