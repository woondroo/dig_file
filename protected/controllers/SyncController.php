<?php
/**
 * Synchronize Controller
 * 
 * @author wengebin
 * @date 2014-01-06
 */
class SyncController extends BaseController
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
		exit();
	}

	/**
	 * Start sync
	 */
	public function actionStart()
	{
		$os = DIRECTORY_SEPARATOR=='\\' ? "windows" : "linux";
		$mac_addr = new CMac( $os );

		$strRKEY = '';
		if ( file_exists( WEB_ROOT.'/js/RKEY.TXT' ) )
		{
			$strRKEY = file_get_contents( WEB_ROOT.'/js/RKEY.TXT' );
		}

		$indexController = new IndexController();
		$checkState = $indexController->actionCheck( true );

		$arySyncData = array();
		$arySyncData['key'] = md5($mac_addr->mac_addr.'-'.$strRKEY);
		$arySyncData['time'] = time();
		$arySyncData['data'] = array();
		$arySyncData['data']['sync']['st'] = count( $checkState['alived'] ) > 0 ? ( $checkState['super'] === true ? 2 : 1 ) : -1;
		$arySyncData['data']['sync']['sp'] = array( 'btc'=>0 , 'ltc'=>0 );
		$arySyncData['data']['sync']['ve'] = CUR_VERSION;
		$arySyncData['data'] = urlencode( base64_encode( json_encode( $arySyncData['data'] ) ) );

		// sync data
		$aryCallBack = UtilApi::callSyncData( $arySyncData );
		if ( $aryCallBack['ISOK'] === 0 )
		{
			echo '500';
			exit();
		}

		$syncData = $aryCallBack['DATA']['sync'];
		if ( empty( $syncData ) )
		{
			echo '500';
			exit();
		}

		$syncData = json_decode( base64_decode( urldecode( $syncData ) ) , 1 );
		if ( !empty( $syncData['upgrade'] ) )
		{
			$strVersion = $syncData['upgrade'];
			if ( !empty( $strVersion ) && $strVersion > CUR_VERSION )
			{
				// execute upgrade
				$command = SUDO_COMMAND."cd ".WEB_ROOT.";".SUDO_COMMAND."wget ".MAIN_DOMAIN."/down/v{$strVersion}.zip;".SUDO_COMMAND."unzip -o v{$strVersion}.zip;".SUDO_COMMAND."rm -rf v{$strVersion}.zip;";
				exec( $command );
			}
		}

		if ( !empty( $syncData['config'] ) )
		{
			$aryConfig = json_decode( $syncData['config'] , 1 );

			$aryBTCData = array();
			$aryBTCData['ad'] = $aryConfig['address_btc'];
			$aryBTCData['ac'] = $aryConfig['account_btc'];
			$aryBTCData['pw'] = $aryConfig['password_btc'];
			$aryBTCData['su'] = isset( $aryConfig['super_btc'] ) ? $aryConfig['super_btc'] : 1;

			$aryLTCData = array();
			$aryLTCData['ad'] = $aryConfig['address_ltc'];
			$aryLTCData['ac'] = $aryConfig['account_ltc'];
			$aryLTCData['pw'] = $aryConfig['password_ltc'];
			$aryLTCData['su'] = isset( $aryConfig['super_ltc'] ) ? $aryConfig['super_ltc'] : 1;

			// store data
			$redis = $this->getRedis();
			$redis->writeByKey( 'btc.setting' , json_encode( $aryBTCData ) );
			$redis->writeByKey( 'ltc.setting' , json_encode( $aryLTCData ) );
		}

		if ( !empty( $syncData['restart'] ) && $syncData['restart'] === 1 )
		{
			$indexController->actionRestart();
		}

		echo '200';
		exit();
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

//end class
}
