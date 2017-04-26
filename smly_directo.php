<?php
/**
 * @file Directo customer import class.
 *
 * @author Ra Mänd <ram4nd@gmail.com>
 *
 * Example:
 * require_once 'smly_directo.php'
 * $directo = new smly_directo($url, $key);
 *
 * $response = $directo->curl_get_xml_object(
 *   'what',
 *   date('d.m.Y', date('yesterday'))
 * );
 */

class smly_directo
{
  private $url;
  private $key;

  public $errors = array();

  public function __construct($url, $key) {
    $this->url = $url;
    $this->key = $key;
  }

  /**
   * @param $what
   * @param $ts
   *
   * @return SimpleXMLElement
   */
  public function curl_get_xml_object($what, $ts) {
    $query = urldecode(http_build_query(array(
      'get' => 1,
      'what' => $what,
      'ts' => $ts,
      'key' => $this->key,
    )));

    // Curl request to receive the xml.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->url . '?' . $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    $response = curl_exec($ch);
    curl_close($ch);

    return simplexml_load_string($response);
  }

  /**
   * @param $what
   * @param $xml_array
   *
   * @return void
   */
  public function curl_post($what, $xml_array) {
    $xml =
      '<?xml version="1.0" encoding="utf-8"?>'."\n".
      $this->_format_xml_elements($xml_array);
    $query = http_build_query(array(
      'put' => 1,
      'what' => $what,
      'xmldata' => $xml,
    ), null, '&');

    // Curl request to receive the xml.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    $this->_error(curl_exec($ch));
    curl_close($ch);
  }

  public function print_all_data($timestamp) {
    // Import data from directo.
    $response = $this->curl_get_xml_object(
      'customer',
      date('d.m.Y', $timestamp)
    );

    foreach ($response->xpath('//customer') as $key => $customer) {
      $attributes = $customer->attributes();
      $attributes = reset($attributes);
      print_r($attributes);
      $attributes = null;

      foreach ($customer->xpath('datafields/data') as $data) {
        $data_array = $data->attributes();
        $data_array = reset($data_array);
        print_r($data_array);
        $data_array = null;
        $data = null;
      }
    }
  }

  private function _error($msg) {
    $this->errors[] = $this->url . ' - ' . date('d.m.Y H:i:s') . ': ' . $msg;
  }

  private function _format_xml_elements($array) {
    $output = '';
    foreach ($array as $key => $value) {
      if (is_numeric($key)) {
        if ($value['key']) {
          $output .= ' <' . $value['key'];
          if (isset($value['attributes']) && is_array($value['attributes'])) {
            $output .= $this->_attributes($value['attributes']);
          }

          if (isset($value['value']) && $value['value'] != '') {
            $output .= '>' . (is_array($value['value']) ? $this->_format_xml_elements($value['value']) : $this->_check_plain($value['value'])) . '</' . $value['key'] . ">\n";
          }
          else {
            $output .= " />\n";
          }
        }
      }
      else {
        $output .= ' <' . $key . '>' . (is_array($value) ? $this->_format_xml_elements($value) : $this->_check_plain($value)) . "</$key>\n";
      }
    }
    return $output;
  }

  private function _attributes(array $attributes = array()) {
    foreach ($attributes as $attribute => &$data) {
      $data = implode(' ', (array) $data);
      $data = $attribute . '="' . $this->_check_plain($data) . '"';
    }
    return $attributes ? ' ' . implode(' ', $attributes) : '';
  }
  
  private function _check_plain($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }

}
