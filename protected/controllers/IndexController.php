<?php
/**
 * Index Controller
 * 
 * @author wengebin
 * @date 2013-12-15
 */
class IndexController extends BaseController
{
	// redis object
	private $_redis;

	// curent every usb setting
	private $_usbSet = array();

	/**
	 * init
	 */
	public function init()
	{
		parent::init();		
	}
	
	/**
	 * Index method
	 */
	public function actionIndex()
	{
		try
		{
			$this->replaceSeoTitle( 'BTC & LTC 挖矿设置' );

			// open redis
			$redis = $this->getRedis();

			// Tip data
			$aryTipData = array();
			$aryBTCData = array();
			$aryLTCData = array();

			$btcVal = $redis->readByKey( 'btc.setting' );
			$ltcVal = $redis->readByKey( 'ltc.setting' );
			$aryBTCData = empty( $btcVal ) ? array() : json_decode( $btcVal , true );
			$aryLTCData = empty( $ltcVal ) ? array() : json_decode( $ltcVal , true );

			// if commit save
			if ( Nbt::app()->request->isPostRequest )
			{
				$strBTCAddress = isset( $_POST['address_btc'] ) ? htmlspecialchars( $_POST['address_btc'] ) : '';
				$strBTCAccount = isset( $_POST['account_btc'] ) ? htmlspecialchars( $_POST['account_btc'] ) : '';
				$strBTCPassword = isset( $_POST['password_btc'] ) ? htmlspecialchars( $_POST['password_btc'] ) : '';

				$strLTCAddress = isset( $_POST['address_ltc'] ) ? htmlspecialchars( $_POST['address_ltc'] ) : '';
				$strLTCAccount = isset( $_POST['account_ltc'] ) ? htmlspecialchars( $_POST['account_ltc'] ) : '';
				$strLTCPassword = isset( $_POST['password_ltc'] ) ? htmlspecialchars( $_POST['password_ltc'] ) : '';

				$aryBTCData['ad'] = $strBTCAddress;
				$aryBTCData['ac'] = $strBTCAccount;
				$aryBTCData['pw'] = $strBTCPassword;
				$aryBTCData['su'] = isset( $aryBTCData['su'] ) ? $aryBTCData['su'] : 0;

				$aryLTCData['ad'] = $strLTCAddress;
				$aryLTCData['ac'] = $strLTCAccount;
				$aryLTCData['pw'] = $strLTCPassword;
				$aryLTCData['su'] = isset( $aryLTCData['su'] ) ? $aryLTCData['su'] : 0;

				// store data
				$redis->writeByKey( 'btc.setting' , json_encode( $aryBTCData ) );
				$redis->writeByKey( 'ltc.setting' , json_encode( $aryLTCData ) );
				$redis->saveData();
				
				$aryTipData['status'] = 'success';
				$aryTipData['text'] = '保存成功!';
			}
		} catch ( Exception $e ) {
			$aryTipData['status'] = 'error';
			$aryTipData['text'] = '保存失败!';
		}

		$aryData = array();
		$aryData['tip'] = $aryTipData;
		$aryData['btc'] = $aryBTCData;
		$aryData['ltc'] = $aryLTCData;
		$this->render( 'index' , $aryData );
	}

	/**
	 * restart program
	 */
	public function actionRestart( $_boolIsNoExist = false )
	{
		// get run model
		$strRunModel = RunModel::model()->getRunModel();

		// shutdown all machine
		$this->actionShutdown( true );

		// restart power
		CPowerSystem::restartPower( 1000000 );

		$aryUsbCache = UsbModel::model()->getUsbChanging( $strRunModel );
		$aryUsb = $aryUsbCache['usb'];

		// if btc machine has restart
		if ( count( $aryUsb ) > 0 && in_array( $strRunModel , array( 'B' , 'LB' ) ) )
		{
			$aryBTCData = $this->getTarConfig( 'btc' );

			$aryConfig = $aryBTCData;
			$aryConfig['ac'] = array_shift( $aryConfig['ac'] );
			$aryConfig['mode'] = $strRunModel === 'LB' ? 'LB-B' : 'B';
			$this->restartByUsb( $aryConfig , 'all' , $strRunModel );
			
			if ( $strRunModel === 'LB' )
				UsbModel::model()->getUsbChanging( $strRunModel , 2 , 'tty' );
		}

		// if ltc machine has restart
		if ( count( $aryUsb ) > 0 && in_array( $strRunModel , array( 'L' , 'LB' ) ) )
		{
			$aryLTCData = $this->getTarConfig( 'ltc' );

			$intUids = $aryLTCData['acc'];
			foreach ( $aryUsb as $usb )
			{
				$aryConfig = $aryLTCData;
				if ( $intUids < 1 )
					$intUids = $aryLTCData['acc'];

				$aryConfig['ac'] = $aryLTCData['ac'][$aryLTCData['acc']-$intUids];
				$aryConfig['mode'] = $strRunModel === 'LB' ? 'LB-L' : 'L';

				$this->restartByUsb( $aryConfig , $usb , $strRunModel );
				$intUids --;
			}
		}

		if ( $_boolIsNoExist === false )
		{
			echo '200';exit;
		}
		else return true;
	}

	/**
	 * restart program by usb
	 */
	public function restartByUsb( $_aryConfig = array() , $_aryUsb = '' , $_strUsbModel = '' , $_strSingleShutDown = '' )
	{
		if ( empty( $_aryConfig ) || empty( $_aryUsb ) || empty( $_strUsbModel ) )
			return false;

		$aryData = $_aryConfig;
		$startModel = $_strUsbModel;

		if ( empty( $aryData ) )
			return false;

		// get btc start command
		if ( in_array( $startModel , array( 'B' , 'LB' ) ) && in_array( $aryData['mode'] , array( 'LB-B' , 'B' ) ) )
			$command = SUDO_COMMAND.WEB_ROOT."/soft/cgminer --dif --gridseed-options=baud=115200,freq=".($aryData['su'] == 0 ? '600' : '700').",chips=5,modules=1,usefifo=0 --hotplug=0 -o {$aryData['ad']} -u {$aryData['ac']} -p {$aryData['pw']} {$startUsb} >/dev/null 2>&1 &";
		// get ltc start command
		else if ( in_array( $startModel , array( 'L' , 'LB' ) ) && in_array( $aryData['mode'] , array( 'LB-L' , 'L' ) ) )
		{
			$modelLParam = $startModel === 'L' ? " -G {$_aryUsb} --freq=".($aryData['su'] == 0 ? '600' : '700') : "";
			$modelLBParam = $startModel === 'LB' ? " --dual" : "";
			$command = SUDO_COMMAND.WEB_ROOT."/soft/minerd{$modelLParam} --dif={$_aryUsb} -o {$aryData['ad']} -u {$aryData['ac']} -p {$aryData['pw']}{$modelLBParam} >/dev/null 2>&1 &";
		}

		exec( $command );
		return true;
	}

	/**
	 * shutdown program
	 */
	public function actionShutdown( $_boolIsNoExist = false , $_strSingleShutDown = '' )
	{
		// get cgminer and cpumienr run command
		$command = SUDO_COMMAND.'ps'.( SUDO_COMMAND === '' ? '' : ' -x' ).'|grep miner';
		exec( $command , $output );

		$pids = array();
		$singlePids = array();
		foreach ( $output as $r )
		{
			preg_match( '/\s*(\d+)\s*.*/' , $r , $match );
			if ( !empty( $match[1] ) ) $pids[] = $match[1];

			if ( !empty( $_strSingleShutDown ) )
			{
				preg_match( '/.*-G\s(.+?)\s--.*/' , $r , $match_usb );
				if ( in_array( $_strSingleShutDown , array( $match_usb[1] ) ) ) $singlePids[] = $match[1];
			}
		}

		if ( !empty( $_strSingleShutDown ) )
			exec( SUDO_COMMAND.'kill -9 '.implode( ' ' , $singlePids ) );
		else if ( !empty( $pids ) )
			exec( SUDO_COMMAND.'kill -9 '.implode( ' ' , $pids ) );
		
		if ( $_boolIsNoExist === false )
		{
			echo '200';exit;
		}
		else return true;
	}

	/**
	 * check state
	 */
	public function actionCheck( $_boolIsNoExist = false )
	{
		// get run model
		$strRunModel = RunModel::model()->getRunModel();

		$command = SUDO_COMMAND.'ps'.( SUDO_COMMAND === '' ? '' : ' -x' ).'|grep miner';
		exec( $command , $output );

		// default null object
		$alived = array('BTC'=>array(),'LTC'=>array());
		$died = array('BTC'=>array(),'LTC'=>array());

		// Alived machine
		$alivedLTCUsb = array();

		// get usb machine and run model
		$aryUsb = UsbModel::model()->getUsbCheckResult( $strRunModel );
		$allUsbCache = $aryUsb['usb'];

		$alivedBTC = false;
		$alivedLTC = false;

		foreach ( $output as $r )
		{
			preg_match( '/.*(cgminer).*/' , $r , $match_btc );
			preg_match( '/.*(minerd).*/' , $r , $match_ltc );

			// if LTC model
			if ( empty( $match_btc[1] ) && !empty( $match_ltc[1] ) && $alivedLTC === false )
				$alivedLTC = true;
			// if BTC model
			else if ( !empty( $match_btc[1] ) && empty( $match_ltc[1] ) && $alivedBTC === false )
				$alivedBTC = true;

			// If BTC model
			if ( !empty( $match_btc[1] ) )
				continue;

			// If LTC model, and LB model running
			if ( !empty( $match_ltc[1] ) && $strRunModel === 'LB' )
				continue;

			// Match all usb machine
			preg_match( '/.*\s-G\s(.+?)\s--.*/' , $r , $match_usb );

			// If LTC model only, and usb cannot use
			if ( !empty( $match_usb[1] ) && !in_array( $match_usb[1] , $allUsbCache ) )
			{
				$this->actionShutdown( true , $match_usb[1] );
				continue;
			}

			if ( !empty( $match_ltc[1] ) && !empty( $match_usb[1] ) )
			{
				if ( !in_array( $match_usb[1] , $alivedLTCUsb ) )
					$alivedLTCUsb[] = $match_usb[1];
			}
		}

		// if has btc model
		if ( in_array( $strRunModel , array( 'B' , 'LB' ) ) && $alivedBTC === true )
		{
			$alived['BTC'] = $allUsbCache;
			$alivedLTC = true;
		}

		// if has btc model
		if ( in_array( $strRunModel , array( 'L' , 'LB' ) ) && $alivedLTC === true )
			$alived['LTC'] = $strRunModel === 'LB' ? $allUsbCache : $alivedLTCUsb;

		sort( $alived['BTC'] );
		sort( $alived['LTC'] );

		// Died machine
		if ( in_array( $strRunModel  , array( 'B' , 'LB' ) ) && $alivedBTC === false )
			$died['BTC'] = $allUsbCache;

		if ( in_array( $strRunModel , array( 'L' , 'LB' ) ) )
		{
			$diedUsb = array();
			$checkLTCUsb = $strRunModel === 'LB' ? $allUsbCache : $alivedLTCUsb;
			foreach ( $checkLTCUsb as $usb )
			{
				if ( !in_array( $usb , $diedUsb ) && !in_array( $usb , $alived['LTC'] ) )
					$diedUsb[] = $usb;
			}
			$died['LTC'] = $diedUsb;
		}
		else if ( $strRunModel === 'LB' && $alivedLTC === false )
			$died['LTC'] = $allUsbCache;

		sort( $died['BTC'] );
		sort( $died['LTC'] );
		
		// return data
		$aryData = array();
		$aryData['alived'] = $alived;
		$aryData['died'] = $died;
		$aryData['super'] = $this->getSuperModelState();

		if ( $_boolIsNoExist === false )
		{
			echo json_encode( $aryData );exit;
		}
		else 
			return $aryData;
	}

	/**
	 * check run state
	 */
	public function actionCheckrun()
	{
		$redis = $this->getRedis();
		$upstatus = json_decode( $redis->readByKey( 'upgrade.run.status' ) , 1 );

		if ( $upstatus['status'] == 1 )
		{
			echo '0';
			exit;
		}
		
		// check upgrade file
		RunModel::model()->checkUpgrade();

		// parse log
		$this->clearLog();

		// reset usb state
		$this->actionUsbstate( true );

		// check data
		$aryData = $this->actionCheck( true );

		// get run model
		$strRunModel = RunModel::model()->getRunModel();
		
		// if need restart
		if ( count( $aryData['alived']['LTC'] ) === 0 
				&& count( $aryData['died']['LTC'] ) > 0 
				&& $strRunModel === 'L' )
			echo $this->actionRestart( true ) === true ? 1 : -1;
		else
			echo 1;
		exit;
	}

	/**
	 * check usb state
	 */
	public function actionUsbstate( $_boolIsReturn = false )
	{
		//$redis = $this->getRedis();
		//$usbVal = $redis->readByKey( 'usb.status' );

		$usbData = array();

		// get run model
		$strRunModel = RunModel::model()->getRunModel();
		
		// B model don't support auto restart
		if ( in_array( $strRunModel , array( 'B' , 'LB' ) ) )
		{
			/*
			$usbData = array();
			if ( $strRunModel === 'B' )
			{
				$usbData['BTC'] = $aryAllUsb;
				$usbData['LTC'] = array();
			}
			else if ( $strRunModel === 'LB' )
			{
				$usbData['BTC'] = $aryAllUsb;
				$usbData['LTC'] = $aryAllUsb;
			}

			// write usb status to cache
			$redis->writeByKey( 'usb.status' , json_encode( $usbData ) );
			*/
		}
		else if ( $strRunModel === 'L' )
		{
			// find new usb machine
			$aryUsbCache = UsbModel::model()->getUsbCheckResult( $strRunModel );
			$aryUsb = $aryUsbCache['usb'];

			// get running programe
			$command = SUDO_COMMAND.'ps'.( SUDO_COMMAND === '' ? '' : ' -x' ).'|grep miner';
			exec( $command , $grepout );

			$alivedProcess = array();
			foreach ( $grepout as $r )
			{
				preg_match( '/.*-G\s(.+?)\s--.*/' , $r , $match_usb );
				if ( !empty( $match_usb[1] ) )
					$alivedProcess[] = $match_usb[1];
			}

			$newUsbData = array('BTC'=>array(),'LTC'=>array());
			foreach ( $alivedProcess as $usb )
			{
				// if usb and process alived
				if ( in_array( $usb , $aryUsb ) )
					$newUsbData['LTC'][] = $usb;
				// if usb not alive,but process alived
				else 
					$this->actionShutdown( true , $usb );
			}
			$usbData = $newUsbData;

			$aryNewMachine = array();
			foreach ( $aryUsb as $r )
			{
				if ( !in_array( $r , $usbData['LTC'] ) )
				{
					$usbData['LTC'][] = $r;
					$aryNewMachine[] = $r;
				}
			}

			if ( count( $aryNewMachine ) > 0 )
			{
				foreach ( $aryNewMachine as $usb )
					$this->actionRestartTarget( $usb , 'ltc' , 'L' , true );
			}

			if ( count( $usbData['LTC'] ) === 0 )
				$this->actionShutdown( true );
		}

		if ( $_boolIsReturn === false )
		{
			echo json_encode( $usbData );
			exit;
		}
		else
			return $usbData;
	}

	/**
	 * restart target usb
	 */
	public function actionRestartTarget( $_strUsb = '' , $_strModel = '' , $_strTo = '' , $_boolIsNoExist = false )
	{
		$setUsbKey = $_strUsb;

		if ( empty( $setUsbKey ) )
		{
			if ( $_boolIsNoExist === true )
				return false;
			else
				echo '500';exit;
		}

		// get config
		$aryConfig = $this->getTarConfig( $_strModel );
		$aryConfig['ac'] = $aryConfig['ac'][rand(0,$aryConfig['acc']-1)];
		$aryConfig['mode'] = $_strTo;

		$this->restartByUsb( $aryConfig , $setUsbKey , $_strTo , $setUsbKey );

		if ( $_boolIsNoExist === true )
				return true;
			else
				echo '500';exit;
	}

	/**
	 * get super model state
	 */
	public function getSuperModelState()
	{
		$aryBTCData = $this->getTarConfig( 'btc' );
		return !empty( $aryBTCData ) && intval( $aryBTCData['su'] ) === 1 ? true : false;
	}

	/**
	 * get redis connection
	 */
	public function getRedis()
	{
		if ( empty( $this->_redis ) )
			$this->_redis = new CRedisFile();

		return $this->_redis;
	}

	/**
	 * read default config
	 */
	public function readDefault( $_strTar = '' )
	{
		if ( empty( $_strTar ) )
			return array();

		// Get key
		$os = DIRECTORY_SEPARATOR=='\\' ? "windows" : "linux";
		$mac_addr = new CMac( $os );

		$strRKEY = '';
		if ( file_exists( WEB_ROOT.'/js/RKEY.TXT' ) )
			$strRKEY = file_get_contents( WEB_ROOT.'/js/RKEY.TXT' );

		$strGenerateKey = substr( $_strTar , 0 , 1 ).substr( md5($mac_addr->mac_addr.'-'.$strRKEY) , -10 , 10 );

		$redis = $this->getRedis();
		$strVal = $redis->readByKey( "default.{$_strTar}.setting" );
		$strVal = str_replace( '******' , $strGenerateKey , $strVal );
		return empty( $strVal ) ? array() : json_decode( $strVal , 1 );
	}

	/**
	 * Setting is empty?
	 */
	public function isEmptySetting( $_arySetting = array() )
	{
		if ( empty( $_arySetting['ad'] ) || empty( $_arySetting['ac'] ) )
			return true;
		else
			return false;
	}

	/**
	 * Get config
	 */
	public function getTarConfig( $_strTar = '' )
	{
		if ( empty( $_strTar ) )
			return array();

		// get config
		$redis = $this->getRedis();
		$setVal = $redis->readByKey( "{$_strTar}.setting" );
		$aryData = empty( $setVal ) ? array() : json_decode( $setVal , true );

		if ( $this->isEmptySetting( $aryData ) )
			$aryData = $this->readDefault( $_strTar );

		// parse account
		$strUids = $aryData['ac'];
		$aryUids = explode( ',' , $strUids );
		$aryUidsSet = array();
		foreach ( $aryUids as $id )
		{
			if ( !empty( $id ) )
				$aryUidsSet[] = $id;
		}

		$aryData['ac'] = $aryUidsSet;
		$aryData['acc'] = count( $aryUidsSet );
		return $aryData;
	}

	/**
	 * Get next usb match user
	 */
	public function getUsbTarUser()
	{
		
	}

	/**
	 * clear log
	 */
	public function clearLog()
	{
		$strRunModel = RunModel::model()->getRunModel();
		$aryUsbCache = UsbModel::model()->getUsbCheckResult( $strRunModel );
		$aryUsb = $aryUsbCache['usb'];

		$redis = $this->getRedis();
		$speedLog = $redis->readByKey( 'speed.log' );
		$countLog = $redis->readByKey( 'speed.count.log' );

		$speedData = json_decode( $speedLog , 1 );
		$countData = json_decode( $countLog , 1 );

		// array( 'BTC'=>array('A'=>100,'R'=>2,'T'=>123456),'LTC'=>array('/dev/ttyUSB0'=>array('A'=>100,'R'=>2,'T'=>123456)) )
		if ( empty( $speedLog ) || empty( $speedData ) )
			$speedData = array('BTC'=>array(),'LTC'=>array());
		if ( empty( $countLog ) || empty( $countData ) )
			$countData = array(
					'BTC'=>array('A'=>0,'R'=>0,'T'=>time()),
					'LTC'=>array('A'=>0,'R'=>0,'T'=>time())
					);

		// every 30 second clear
		$now = time();
		if ( !empty( $speedData['lastlog'] ) && $now - $speedData['lastlog'] < 30 )
			return false;

		$newData = array('BTC'=>array(),'LTC'=>array());
		if ( in_array( $strRunModel , array( 'B' , 'LB' ) ) )
			$newData['BTC'] = $speedData['BTC'];

		if ( in_array( $strRunModel , array( 'L' , 'LB' ) ) )
		{
			foreach ( $speedData['LTC'] as $k=>$d )
			{
				if ( in_array( $k , $aryUsb ) )
					$newData['LTC'][$k] = $d;
			}
		}

		if ( in_array( $strRunModel , array( 'L' , 'LB' ) ) )
		{
			foreach ( $aryUsb as $usb )
			{
				if ( !array_key_exists( $usb , $newData['LTC'] ) )
					$newData['LTC'][$usb] = array( 'A'=>0 , 'R'=>0 , 'T'=>$now );
			}
		}

		$log_dir = '/tmp';
		$btc_log_dir = $log_dir.'/btc';
		$ltc_log_dir = $log_dir.'/ltc';

		$boolIsNeedRestart = false;
		
		$btc_dir_source = opendir( $btc_log_dir );
		$btc_need_check_time = false;
		while ( ( $file  = readdir( $btc_dir_source ) ) !== false )
		{
			// 获得子目录
			$sub_dir = $btc_log_dir.DIRECTORY_SEPARATOR.$file;
			if ( $file == '.' || $file == '..' )
				continue;
			else
			{
				$val = file_get_contents( $sub_dir );
				$valData = explode( '|', $val );
				
				if ( $valData[2] == 'A' )
				{
					$newData['BTC']['A'] ++;
					$countData['BTC']['A'] ++;
				}
				else if ( $valData['2'] == 'R' )
				{
					$newData['BTC']['R'] ++;
					$countData['BTC']['R'] ++;
				}

				//if ( intval( $valData[1] ) > $newData['BTC']['T'] )
				$newData['BTC']['T'] = time();
				$countData['BTC']['T'] = time();

				// hard
				// $valData['3']
				// machine
				// $valData['0']

				unlink( $sub_dir );
				$btc_need_check_time = true;
			}
		}

		// is need restart
		if ( in_array( $strRunModel , array( 'B' , 'LB' ) ) && $btc_need_check_time )
			if ( $now - $newData['BTC']['T'] > 600 )
				$boolIsNeedRestart = true;

		$ltc_dir_source = opendir( $ltc_log_dir );
		$ltc_need_check_time = false;
		while ( ( $file  = readdir( $ltc_dir_source ) ) !== false )
		{
			// 获得子目录
			$sub_dir = $ltc_log_dir.DIRECTORY_SEPARATOR.$file;
			if ( $file == '.' || $file == '..' )
				continue;
			else
			{
				$val = file_get_contents( $sub_dir );
				$valData = explode( '|', $val );

				// machine id
				$id = $valData[0];

				if ( !array_key_exists( $id , $newData['LTC'] ) )
				{
					unlink( $sub_dir );
					continue;
				}
			
				if ( $valData[2] == 'A' )
				{
					$newData['LTC'][$id]['A'] ++;
					$countData['LTC']['A'] ++;
				}
				else if ( $valData['2'] == 'R' )
				{
					$newData['LTC'][$id]['R'] ++;
					$countData['LTC']['R'] ++;
				}

				//if ( intval( $valData[1] ) > $newData['LTC'][$id]['T'] )
				$newData['LTC'][$id]['T'] = time();
				$countData['LTC']['T'] = time();

				// hard
				// $valData['3']

				unlink( $sub_dir );
				$ltc_need_check_time = true;
			}
		}

		if ( in_array( $strRunModel , array( 'L' , 'LB' ) ) && $ltc_need_check_time )
		{
			foreach ( $newData['LTC'] as $m )
			{
				if ( $now - $m['T'] > 600 )
				{
					$boolIsNeedRestart = true;
					break;
				}
			}
		}

		if ( empty( $speedData['lastlog'] ) )
			$boolIsNeedRestart = false;

		// store clear time stamp
		$newData['lastlog'] = $now+300;

		// write log
		$redis->writeByKey( 'speed.log' , json_encode( $newData , 1 ) );
		$redis->writeByKey( 'speed.count.log' , json_encode( $countData , 1 ) );

		// if need restart
		if ( $boolIsNeedRestart === true )
			$this->actionRestart( true );

		return true;
	}

//end class
}
