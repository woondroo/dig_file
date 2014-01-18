<?php
/**
 * USB操作类
 *
 * @author wengebin
 * @package framework
 * @date 2014-01-18
 */
class UsbModel extends CModel
{
	// redis object
	private $_redis;

	/**
	 * 初始化
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * 返回惟一实例
	 *
	 * @return Model
	 */
	public static function model( $className = __CLASS__ )
	{
		return parent::model( __CLASS__ );
	}

	/**
	 * USB获得检测结果
	 */
	public function getUsbCheckResult( $_strRunModel = '' , $_strCheckTar = '' )
	{
		if ( empty( $_strRunModel ) )
			return array();

		if ( in_array( $_strRunModel , array( 'B' , 'LB' ) ) )
		{
			$redis = $this->getRedis();
			$aryUsbCache = json_decode( $redis->readByKey( 'usb.check.result' ) , 1 );
			
			if ( empty( $aryUsbCache ) )
				$aryUsbCache = array( 'usb'=>array() , 'time'=>0 , 'iswrite'=>0 );

			$now = time();
			// if usb state time out
			if ( ( $now - $aryUsbCache['time'] > 5 && empty( $aryUsbCache['iswrite']  ) && empty( $_strCheckTar ) ) || $_strCheckTar === 'lsusb' )
			{
				$aryUsbCache['iswrite'] = 1;
				$redis->writeByKey( 'usb.check.result' , json_encode( $aryUsbCache , 1 ) );

				@exec( SUDO_COMMAND.'lsusb' , $output );

				$aryUsb = array();
				foreach ( $output as $usb )
				{
					preg_match( '/.*Bus\s(\d+)\sDevice\s(\d+).*CP210x.*/' , $usb , $match_usb );
					if ( !empty( $match_usb[1] ) && !empty( $match_usb[2] ) )
					{
						$strId = intval( $match_usb[1] ).':'.intval( $match_usb[2] );
						$aryUsb[] = $strId;
					}
				}

				// store usb state
				$aryUsbCache['usb'] = $aryUsb;
				$aryUsbCache['time'] = time();
				$aryUsbCache['iswrite'] = 0;
				$redis->writeByKey( 'usb.check.result' , json_encode( $aryUsbCache , 1 ) );
			}
		}
		else if ( empty( $_strCheckTar ) || $_strCheckTar === 'tty' )
		{
			@exec( SUDO_COMMAND.'ls /dev/*USB*' , $output );

			$aryUsbCache = array();
			$aryUsbCache['usb'] = $output;
			$aryUsbCache['time'] = time();

			return $aryUsbCache;
		}

		return $aryUsbCache;
	}

	/**
	 * check usb is changing
	 */
	public function getUsbChanging( $_strRunModel = '' , $_intSetTimer = 0 , $_strCheckTar = '' )
	{
		if ( empty( $_strRunModel ) )
			return array();

		$aryUsbCache = $this->getUsbCheckResult( $_strRunModel , $_strCheckTar );
		$continue = true;

		$timer = in_array( $_strRunModel , array( 'B' , 'LB' ) ) ? 6 : 2;
		if ( $_intSetTimer > 0 )
			$timer = $_intSetTimer;

		$time_last = $timer;

		// when has more machine change
		while( $continue )
		{
			sleep( 1 );
			$time_last --;

			$newAryUsbCache = $this->getUsbCheckResult( $_strRunModel , $_strCheckTar );

			if ( count( $newAryUsbCache['usb'] ) != count( $aryUsbCache['usb'] ) )	
				$time_last = $timer;

			if ( $time_last === 0 )
				$continue = false;

			$aryUsbCache = $newAryUsbCache;
		}

		return $aryUsbCache;
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
