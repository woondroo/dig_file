<?php
/**
 * Index Controller
 * 
 * @author wengebin
 * @date 2013-12-15
 */
class IndexController extends BaseController
{
	private $_redis;

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

		$redis = $this->getRedis();
		$btcVal = $redis->readByKey( 'btc.setting' );
		$ltcVal = $redis->readByKey( 'ltc.setting' );
		if ( empty( $btcVal ) )
			$btcVal = $this->readDefault( 'btc' );
		if ( empty( $ltcVal ) )
			$ltcVal = $this->readDefault( 'ltc' );

		$aryBTCData = empty( $btcVal ) ? array() : json_decode( $btcVal , true );
		$aryLTCData = empty( $ltcVal ) ? array() : json_decode( $ltcVal , true );

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
		if ( empty( $usbData['BTC'] ) )
		{
			if ( $_boolIsNoExist === false )
			{
				echo '200';exit;
			}
			else return true;
		}

		// if btc machine has restart
/*
		if ( count( $usbData['BTC'] ) > 0 )
		{
			$this->restartByUsb( $usbData['BTC'] , 'btc' );
			sleep( 3 );
		}

		if ( count( $usbData['LTC'] ) > 0 )
			$this->restartByUsb( $usbData['LTC'] , 'ltc' );
*/

		if ( count( $usbData['LTC'] ) > 0 )
		{
			foreach ( $usbData['LTC'] as $usb )
				$this->restartByUsb( $usb , 'ltc' );
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
	public function restartByUsb( $_aryUsb = '' , $_strUsbModel = '' , $_strSingleShutDown = '' )
	{
		if ( empty( $_aryUsb ) || empty( $_strUsbModel ) )
			return false;

		$startModel = $_strUsbModel;
/*
		$startUsb = '-S '.implode( ' -S ' , $_aryUsb );
		if ( $startModel == 'ltc' )
			$startUsb = $_aryUsb[0];
*/

		$redis = $this->getRedis();
		$setVal = $redis->readByKey( "{$startModel}.setting" );
		if ( empty( $setVal ) )
			$setVal = $this->readDefault( "{$startModel}" );

		$aryData = empty( $setVal ) ? array() : json_decode( $setVal , true );
		if ( empty( $aryData ) )
			return false;

		if ( $startModel == 'btc' )
			$command = SUDO_COMMAND.WEB_ROOT."/soft/cgminer --icarus-options=115200:2:".($aryData['su'] == 0 ? '600' : '700')." -o {$aryData['ad']} -u {$aryData['ac']} -p {$aryData['pw']} {$startUsb} >/dev/null 2>&1 &";
		else if ( $startModel == 'ltc' )
			//$command = SUDO_COMMAND.WEB_ROOT."/soft/minerd --freq=".($aryData['su'] == 0 ? '600' : '700')." --gc3355={$startUsb} --url={$aryData['ad']} --userpass={$aryData['ac']}:{$aryData['pw']} --dual -t ".count( $_aryUsb )." >/dev/null 2>&1 &";
			$command = SUDO_COMMAND.WEB_ROOT."/soft/minerd --freq=".($aryData['su'] == 0 ? '600' : '700')." --gc3355={$_aryUsb} --url={$aryData['ad']} --userpass={$aryData['ac']}:{$aryData['pw']} >/dev/null 2>&1 &";

		exec( $command );
		return true;
	}

	/**
	 * shutdown program
	 */
	public function actionShutdown( $_boolIsNoExist = false , $_strSingleShutDown = '' )
	{
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
				preg_match( '/.*--gc3355=(.+?)\s--.*/' , $r , $match_usb );
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
		$alivedUsb = array();
		$alivedBTC = false;
		$alivedLTC = false;
		foreach ( $output as $r )
		{
			preg_match( '/.*(cgminer).*/' , $r , $match_btc );
			preg_match( '/.*(minerd).*/' , $r , $match_ltc );

			// if LTC model
			/*
			if ( empty( $match_btc[1] ) && !empty( $match_ltc[1] ) )
			{
				$alivedLTC = true;
				continue;
			}
			*/
			// if BTC model
			//else if ( !empty( $match_btc[1] ) && empty( $match_ltc[1] ) )
			//	$alivedBTC = true;
				
			// Match all usb machine
			//preg_match_all( '/\s-S\s([^\s]+)/' , $r , $match_usb );
			preg_match( '/.*--gc3355=(.+?)\s--.*/' , $r , $match_usb );

			// Get Matched machine
			//$match_all_usb_ary = $match_usb[1];

			// If BTC model and no usb machine run
			/*
			if ( !empty( $match_btc[1] ) && empty( $match_all_usb_ary ) )
				continue;
			*/
			if ( !empty( $match_usb[1] ) && !in_array( $match_usb[1] , $usbData['LTC'] ) )
			{
				$this->actionShutdown( true , $match_usb[1] );
				continue;
			}

			if ( !empty( $match_btc[1] ) )
				continue;

			if ( !empty( $match_ltc[1] ) && !empty( $match_usb[1] ) )
			{
				if ( !in_array( $match_usb[1] , $alivedUsb ) )
					$alivedUsb[] = $match_usb[1];
			}

			/*
			foreach ( $match_all_usb_ary as $usb )
			{
				if ( !in_array( $usb , $alivedUsb ) )
					$alivedUsb[] = $usb;
			}
			*/
		}

		/*
		if ( $alivedBTC === true )
			$alived['BTC'] = $alivedUsb;
		else
			$alived['BTC'] = array();

		if ( $alivedLTC === true )
			$alived['LTC'] = $alivedUsb;
		else
			$alived['LTC'] = array();
		*/

		$alived['BTC'] = $alivedUsb;
		$alived['LTC'] = $alivedUsb;

		sort( $alived['BTC'] );
		sort( $alived['LTC'] );

		// Died machine
		$diedUsb = array();
		foreach ( $usbData['BTC'] as $usb )
		{
			if ( !in_array( $usb , $diedUsb ) && !in_array( $usb , $alivedUsb ) )
				$diedUsb[] = $usb;
		}

		$died['BTC'] = $died['LTC'] = $diedUsb;
		sort( $died['BTC'] );
		sort( $died['LTC'] );
		
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
		// reset usb state
		$this->actionUsbstate();
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
	public function actionUsbstate()
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

		exec( SUDO_COMMAND.'ls /dev/*USB*' , $output );

		$newUsbData = array('BTC'=>array(),'LTC'=>array());
		foreach ( $usbData['BTC'] as $usb )
		{
			if ( in_array( $usb , $output ) )
			{
				$newUsbData['BTC'][] = $usb;
				$newUsbData['LTC'][] = $usb;
			}
		}
		$usbData = $newUsbData;

		$aryNewMachine = array();
		foreach ( $output as $r )
		{
			if ( !in_array( $r , $usbData['BTC'] ) )
			{
				$usbData['BTC'][] = $r;
				$usbData['LTC'][] = $r;
				$aryNewMachine[] = $r;
			}
		}

		$redis->writeByKey( 'usb.status' , json_encode( $usbData ) );

		if ( count( $aryNewMachine ) > 0 )
		{
			foreach ( $aryNewMachine as $usb )
				$this->actionRestartTarget( $usb , 'ltc' , true );
		}

		//	$this->actionRestart( true );

		if ( count( $usbData['BTC'] ) === 0 && count( $usbData['LTC'] ) === 0 )
			$this->actionShutdown( true );

		echo json_encode( $usbData );
	}

	/**
	 * set usb state
	 */
	/*
	public function actionUsbset()
	{
		$redis = $this->getRedis();
		$usbVal = $redis->readByKey( 'usb.status' );

		$setUsbKey = isset( $_GET['usb'] ) ? htmlspecialchars( $_GET['usb'] ) : '';
		$setUsbTo = isset( $_GET['to'] ) ? htmlspecialchars( $_GET['to'] ) : '';

		if ( empty( $setUsbKey ) || empty( $setUsbTo ) )
		{
			echo '500';exit;
		}

		if ( !empty( $usbVal ) )
			$usbData = json_decode( $usbVal , true );
		else 
		{
			echo '500';exit;
		}

		if ( array_key_exists( $setUsbKey , $usbData ) && in_array( $setUsbTo , array( 'ltc' , 'btc' , '0' ) ) )
			$usbData[ $setUsbKey ] = $setUsbTo;
		else 
		{
			echo '500';exit;
		}

		$redis->writeByKey( 'usb.status' , json_encode( $usbData ) );
		$this->restartByUsb( $setUsbKey , $setUsbTo , $setUsbKey );

		echo '200';exit;
	}
	*/

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

		$this->restartByUsb( $setUsbKey , $_strTo );

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
		$redis = $this->getRedis();
		$btcVal = $redis->readByKey( 'btc.setting' );
		if ( empty( $btcVal ) )
			$btcVal = $this->readDefault( 'btc' );
		$aryBTCData = empty( $btcVal ) ? array() : json_decode( $btcVal , true );

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
		return $strVal;
	}

//end class
}
