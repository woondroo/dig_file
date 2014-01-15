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
	 * super mode
	 */
	public function actionMode()
	{
		// is super mode
		$intIsSuper = isset( $_GET['s'] ) ? intval( $_GET['s'] ) : 0;

		$aryBTCData = $this->getTarConfig( 'btc' );
		$aryLTCData = $this->getTarConfig( 'ltc' );

		if ( $intIsSuper === 1 )
		{
			$aryBTCData['su'] = 1;
			$aryLTCData['su'] = 1;
		}
		else
		{
			$aryBTCData['su'] = 0;
			$aryLTCData['su'] = 0;
		}

		// store data
		unset( $aryBTCData['acc'] );
		unset( $aryLTCData['acc'] );
		$aryBTCData['ac'] = implode( ',' , $aryBTCData['ac'] );
		$aryLTCData['ac'] = implode( ',' , $aryLTCData['ac'] );
		$redis->writeByKey( 'btc.setting' , json_encode( $aryBTCData ) );
		$redis->writeByKey( 'ltc.setting' , json_encode( $aryLTCData ) );

		$this->actionRestart( true );
		echo '200';exit;
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

		$redis = $this->getRedis();
		$usbVal = $redis->readByKey( 'usb.status' );
		if ( empty( $usbVal ) )
		{
			if ( $_boolIsNoExist === false )
			{
				echo '500';exit;
			}
			else return false;
		}

		$usbData = json_decode( $usbVal , true );
		if ( empty( $usbData['BTC'] ) && empty( $usbData['LTC'] ) )
		{
			if ( $_boolIsNoExist === false )
			{
				echo '200';exit;
			}
			else return true;
		}

		// if B or BL model and no btc or ltc message
		if ( in_array( $strRunModel , array( 'B' , 'LB' ) ) )
		{
			if ( empty( $usbData['BTC'] ) || ( empty( $usbData['LTC'] ) && $strRunModel === 'LB' ) )
				$this->actionUsbstate( true );

			$usbData = json_decode( $redis->readByKey( 'usb.status' ) , 1 );
		}

		// if L model and no ltc message
		if ( $strRunModel === 'L' && empty( $usbData['LTC'] ) )
		{
			$this->actionUsbstate( true );
			$usbData = json_decode( $redis->readByKey( 'usb.status' ) , 1 );
		}

		// if btc machine has restart
		$aryBTCData = $this->getTarConfig( 'btc' );
		if ( count( $usbData['BTC'] ) > 0 && in_array( $strRunModel , array( 'B' , 'LB' ) ) )
		{
			$aryConfig = $aryBTCData;
			$aryConfig['ac'] = array_shift( $aryConfig['ac'] );
			$aryConfig['mode'] = $strRunModel === 'LB' ? 'LB-B' : 'B';
			$this->restartByUsb( $aryConfig , 'all' , $strRunModel );
			
			if ( $strRunModel === 'LB' )
				sleep( 5 );
		}

		// if ltc machine has restart
		$aryLTCData = $this->getTarConfig( 'ltc' );
		if ( count( $usbData['LTC'] ) > 0 && in_array( $strRunModel , array( 'L' , 'LB' ) ) )
		{
			$intUids = $aryLTCData['acc'];
			foreach ( $usbData['LTC'] as $usb )
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
			$command = SUDO_COMMAND.WEB_ROOT."/soft/cgminer --gridseed-options=baud=115200,freq=".($aryData['su'] == 0 ? '600' : '700').",chips=5,modules=1,usefifo=0 -o {$aryData['ad']} -u {$aryData['ac']} -p {$aryData['pw']} {$startUsb} >/dev/null 2>&1 &";
		// get ltc start command
		else if ( in_array( $startModel , array( 'L' , 'LB' ) ) && in_array( $aryData['mode'] , array( 'LB-L' , 'L' ) ) )
		{
			$modelLParam = $startModel === 'L' ? " -G {$_aryUsb} --freq=".($aryData['su'] == 0 ? '600' : '700') : "";
			$modelLBParam = $startModel === 'LB' ? " --dual" : "";
			$command = SUDO_COMMAND.WEB_ROOT."/soft/minerd{$modelLParam} -o {$aryData['ad']} -u {$aryData['ac']} -p {$aryData['pw']}{$modelLBParam} >/dev/null 2>&1 &";
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
		$command = SUDO_COMMAND.'ps'.( SUDO_COMMAND === '' ? '' : ' -x' ).'|grep miner';
		exec( $command , $output );

		$alived = array('BTC'=>array(),'LTC'=>array());
		$died = array('BTC'=>array(),'LTC'=>array());

		$redis = $this->getRedis();
		$usbVal = $redis->readByKey( 'usb.status' );

		if ( !empty( $usbVal ) )
			$usbData = json_decode( $usbVal , true );
		else
			$usbData = array();

		// Alived machine
		$alivedLTCUsb = array();

		// If LB model running
		$allUsbCache = RunModel::model()->getAllUsbCache();
		$strRunModel = RunModel::model()->getRunModel();

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
			if ( !empty( $match_usb[1] ) && !in_array( $match_usb[1] , $usbData['LTC'] ) )
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
			foreach ( $usbData['LTC'] as $usb )
			{
				if ( !in_array( $usb , $diedUsb ) && !in_array( $usb , $checkLTCUsb ) )
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
		// check upgrade file
		RunModel::model()->checkUpgrade();

		// reset usb state
		$this->actionUsbstate( true );

		// check data
		$aryData = $this->actionCheck( true );
		
		if ( count( $aryData['alived']['BTC'] ) === 0 && count( $aryData['died']['BTC'] ) > 0 )
		{
			echo $this->actionRestart( true ) === true ? 1 : -1;
		}
		else
			echo 0;
		exit;
	}

	/**
	 * check usb state
	 */
	public function actionUsbstate( $_boolIsReturn = false )
	{
		$redis = $this->getRedis();
		$usbVal = $redis->readByKey( 'usb.status' );

		$usbData = array();
		if ( !empty( $usbVal ) )
		{
			$usbData = json_decode( $usbVal , true );
			if ( empty( $usbData['BTC'] ) ) $usbData['BTC'] = array();
			if ( empty( $usbData['LTC'] ) ) $usbData['LTC'] = array();
		}

		// find new usb machine
		exec( SUDO_COMMAND.'ls /dev/*USB*' , $output );

		// get run model
		$strRunModel = RunModel::model()->getRunModel();

		if ( in_array( $strRunModel , array( 'B' , 'LB' ) ) )
		{
			$aryAllUsb = RunModel::model()->getAllUsbCache();
			if ( count( $output ) > 0 )
			{
				// get running programe
				$command = SUDO_COMMAND.'ps'.( SUDO_COMMAND === '' ? '' : ' -x' ).'|grep miner';
				exec( $command , $grepout );

				// match is programe has running
				$boolIsNotRun = true;
				if ( count( $grepout ) )
				{
					foreach ( $grepout as $g )
					{
						preg_match( '/.*(cgminer).*/' , $r , $match_btc );
						preg_match( '/.*(minerd).*/' , $r , $match_ltc );

						if ( !empty( $match_btc ) || !empty( $match_ltc ) )
							$boolIsNotRun = false;
					}
				}

				// if not run any programe
				if ( $boolIsNotRun === true )
					$aryAllUsb = $output;
				else
					$aryAllUsb = array_merge( $aryAllUsb , $output );

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
				// cache all usb
				RunModel::model()->storeAllUsbCache( $aryAllUsb );
				
				if ( $_boolIsReturn === false )
					$this->actionRestart( true );
			}
			else
			{
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
			}

			$usbData = $aryAllUsb;
		}
		else
		{
			$aryCacheUsb = RunModel::model()->getAllUsbCache();

			if ( count( $output ) > 0 )
			{
				$newUsbData = array('BTC'=>array(),'LTC'=>array());
				foreach ( $usbData['LTC'] as $usb )
				{
					if ( in_array( $usb , $output ) )
						$newUsbData['LTC'][] = $usb;
				}
				$usbData = $newUsbData;

				$aryNewMachine = array();
				foreach ( $output as $r )
				{
					if ( !in_array( $r , $usbData['LTC'] ) )
					{
						$usbData['LTC'][] = $r;
						$aryNewMachine[] = $r;
					}
				}

				$redis->writeByKey( 'usb.status' , json_encode( $usbData ) );

				if ( count( $aryNewMachine ) > 0 )
				{
					foreach ( $aryNewMachine as $usb )
						$this->actionRestartTarget( $usb , 'L' , true );
				}

				if ( count( $usbData['BTC'] ) === 0 && count( $usbData['LTC'] ) === 0 )
					$this->actionShutdown( true );
				}
			else
			{
				$usbData = array( 'BTC'=>array() , 'LTC'=>$aryCacheUsb );
				$redis->writeByKey( 'usb.status' , json_encode( $usbData ) );
			}
		}

		if ( $_boolIsReturn === false )
			echo json_encode( $usbData );
		else
			return $usbData;
	}

	/**
	 * restart target usb
	 */
	public function actionRestartTarget( $_strUsb = '' , $_strTo = '' , $_boolIsNoExist = false )
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
		$aryConfig = $this->getTarConfig( $_strTo );
		$aryConfig['ac'] = $aryConfig['ac'][rand(0,$aryConfig['acc']-1)];

		$this->restartByUsb( $aryConfig , $setUsbKey , $_strTo );

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

//end class
}
