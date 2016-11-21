<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/1/6
 * Time: 10:55
 */

//  现在剩下的问题，就是确定一个 token。用于 encode 与 decode
// create token
function create_token()
{
	// 40 Bytes
	$data = $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR'] . time() . rand();
	return sha1($data);

}


//
class Aes
{

	// CRYPTO_CIPHER_BLOCK_SIZE 32
	private $_secret_key = '1234567890abcdef';

	public function setKey($key)
	{
		$this->_secret_key = $key;
	}

	public function encode($data)
	{
		$td = mcrypt_module_open(MCRYPT_RIJNDAEL_256, '', MCRYPT_MODE_CBC, '');
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, $this->_secret_key, $iv);
		$encrypted = mcrypt_generic($td, $data);
		mcrypt_generic_deinit($td);

		return $iv . $encrypted;
	}

	public function decode($data)
	{
		$td = mcrypt_module_open(MCRYPT_RIJNDAEL_256, '', MCRYPT_MODE_CBC, '');
		$iv = mb_substr($data, 0, 32, 'latin1');
		mcrypt_generic_init($td, $this->_secret_key, $iv);
		$data = mb_substr($data, 32, mb_strlen($data, 'latin1'), 'latin1');
		$data = mdecrypt_generic($td, $data);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);

		return trim($data);
	}
}

//
class Security
{

	public static function encrypt($input, $key)
	{
		$size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
		$input = Security::pkcs5_pad($input, $size);
		$td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, $key, $iv);
		$data = mcrypt_generic($td, $input);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		$data = base64_encode($data);

		return $data;
	}

	private static function pkcs5_pad($text, $blocksize)
	{
		$pad = $blocksize - (strlen($text) % $blocksize);

		return $text . str_repeat(chr($pad), $pad);
	}

	public static function decrypt($sStr, $sKey)
	{
		$decrypted = mcrypt_decrypt(
			MCRYPT_RIJNDAEL_128,
			$sKey,
			base64_decode($sStr),
			MCRYPT_MODE_ECB
		);

		$dec_s = strlen($decrypted);
		$padding = ord($decrypted[ $dec_s - 1 ]);
		$decrypted = substr($decrypted, 0, - $padding);

		return $decrypted;
	}
}
