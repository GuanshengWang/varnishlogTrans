<?php
//error_reporting(E_ALL);


function main(){
  $util = new util();
  
  $fh = fopen('php://stdin','rb'); 
  if(!$fh) {echo "can not open stdin.\n";exit();}
  while ($raw = fgets($fh)){
    $raw = rtrim($raw,"\n"); 
    if($raw == '') continue;

    $rawA = $util->rawDecode($raw);

    if($util->addTrx($rawA)){
      $util->echoData();
      $util->clearRetData();
    
    }
  }
  fclose($fh); 


}

class util{

  protected $tagRep;
  protected $tagVar;
  protected $tmpTrx;
  protected $retData;

  function __construct(){
    $this->retData  =array();
    $this->tmpTrx  = array();

    $this->tagRep  = array(
      'Header'   =>'http',
      'Protocol' =>'proto',
      'Request'  =>'request',
      'Response' =>'response',
      'Status'   =>'status',
      'URL'      =>'url',
    );

    $this->tagVar = array(
      'bRx'    =>'beresp',
      'cRx'    =>'req',
      'bTx'    =>'bereq',
      'cTx'    =>'resp',
      'bObj'   =>'obj',
      'cObj'   =>'obj',
    );

  }

/////////////////////////////////////////////////////
  private function _echoline($s = '-'){
    echo str_repeat($s,60)."\n";
  }
/////////////////////////////////////////////////////
  private function _echo($ar,$pad = 1,$pre = 0,$pret = ''){
    $max = 0;
    foreach ($ar as $k => $v){
      if(!is_null($v['v'])){
        $kl = strlen($v['k']);
        if($kl>$max) $max = $kl;
      }
    }
    $padt = str_repeat(' ',$pad).'|'.str_repeat(' ',$pad);
    foreach ($ar as $k => $v){
      $kl = strlen($v['k']);
      echo str_repeat(' ',$pre).$pret.$v['k'];
      if(!is_null($v['v'])){
        echo str_repeat(' ',$max-$kl);
        echo $padt;
        echo $v['v'];
      }
      echo "\n";
    }
    return $max;
  }


/////////////////////////////////////////////////////
  public function _echoRect($ar){
  /*
    ar
     +key =rect-v
     +value(array)
      +k
      +v
  */
    $maxkey=0;
    //一時計算
    foreach($ar as $v){
      $t = strlen($v['key']);
      if($maxkey < $t)
        $maxkey = $t;
      if(isset($v['title'])){
        $t = strlen($v['title']);
        if($maxkey < $t)
          $maxkey = $t;
        
      }
    }
    $ha = (int)$maxkey/2 +2;
    $_pad = str_repeat(' ',$ha);

    //描画Rect
    foreach($ar as $v){
//    var_dump($v['value']);
      if(isset($v['title'])){
        $d = (int)($maxkey-strlen($v['title']))/2+1;
        echo str_repeat(' ',$d).$v['title']."\n";
      }
      $this->__echoRect($v['key'],$maxkey);
      echo "{$_pad}|\n";
      $this->_echo($v['value'],1,0,$_pad.'| ');
      echo "{$_pad}|\n";
    }

  }
/////////////////////////////////////////////////////
  private function __echoRect($txt,$len,$pad=1){
    $d = (int)($len-strlen($txt))/2;
    $_padp = str_repeat(' ',$pad+$d);
    $_pads = $_padp;
    $ds = ($len-strlen($txt))%2;
    if($ds>0)
      $_pads .= ' ';
    $line = str_repeat('-',$pad*2+$len+2)."\n";
    echo $line;
    echo "|{$_padp}{$txt}{$_pads}|\n";
    echo $line;
  }


/////////////////////////////////////////////////////
  public function searchHeader($search,$o){
    $search .= ': ';
    foreach($o as $v){
      if(0 === strpos($v,$search)){
        $vv = explode(': ',$v,2);
        return($vv[1]);
        break;
      }
      
    }
    return false;
  }
/////////////////////////////////////////////////////
  private function _trace2array($vv){
    $tmp = array();
    switch($vv['type']){
      case 'trace':
        $m = "vrt_count:{$vv['vrt_count']} vcl_line:{$vv['vcl_line']} vcl_pos:{$vv['vcl_pos']}";
        $tmp = array(
          'k' => $vv['type'],
          'v' => $m
        );
        break;
      default:
        $m = $vv['data'];
        $tmp = array(
          'k' => $vv['type'],
          'v' => $m
        );
        break;
    }
    return $tmp;
  }

/////////////////////////////////////////////////////
  public function echoData($no = 0){
    $restart = 0;
    $d = &$this->retData[$no];


    //info
    $tmp = array();

    $tmp[] = array(
      'k' => 'client ip',
      'v' => $d['var'][0]['client']['ip'][0],
    );
    $host = $this->searchHeader('Host',$d['var'][0]['req']['http']);
    if($host)
      $tmp[] = array(
        'k' => 'client request host',
        'v' => $host,
      );
    $tmp[] = array(
      'k' => 'client request url',
      'v' => $d['var'][0]['req']['url'][0],
    );

    $length = 0;
    foreach($d['info']['length'] as $v) $length += $v;
    $tmp[] = array(
      'k' => 'response size',
      'v' => $length.' byte',
    );

    foreach($d['var'] as $v){
      if(isset($v['resp'])){
        $tmp[] = array(
          'k' => 'response status',
          'v' => "{$v['resp']['proto'][0]} {$v['resp']['status'][0]} {$v['resp']['response'][0]}",
        );
        break;
      }
    }
    

    if(isset($d['info']['time.accept']))
      $tmp[] = array(
        'k' => 'Connect time',
        'v' => $d['info']['time.accept'].' sec',
      );
    if(isset($d['info']['time.execute']))
      $tmp[] = array(
        'k' => 'Waiting time',
        'v' => $d['info']['time.execute'].' sec',
      );
    if(isset($d['info']['time.exit']))
      $tmp[] = array(
        'k' => 'Processing time',
        'v' => $d['info']['time.exit'].' sec',
      );
    $tmp[] = array(
      'k' => 'Total time',
      'v' => $d['info']['time.total'].' sec',
    );


    $restart = 0;
    $esi = 0;
    foreach($d['countinfo'] as $v){
      if($v == 'restart'){$restart++;}
      elseif($v == 'esi'){$esi++;}
    }
    $tmp[] = array(
      'k' => 'restart count',
      'v' => $restart,
    );
    $tmp[] = array(
      'k' => 'ESI count',
      'v' => $esi,
    );
    $bname='';
    foreach($d['backend'] as $vz){
      $bname.="{$vz['info']['backend.name']}({$vz['info']['backend.server']}) ";
    }
    $tmp[] = array(
      'k' => 'call backend',
      'v' => count($d['backend']).' '.rtrim($bname),
    );
    $hit = 0;
    $miss = 0;
    foreach($d['call'] as $v){
      if($v['method'] == 'hit'){$hit++;}
      elseif($v['method'] == 'miss'){$miss++;}
    }
    $tmp[] = array(
      'k' => 'Cache',
      'v' => "HIT:{$hit} MISS:{$miss}",
    );


    $this->_echo($tmp);
//////////////////////////
    //calllist
    $this->_echoline();

    $tmp = array();
    foreach($d['call'] as $k => $v){
      $tmpm = array();
///////
      $title=null;
      if($v['method'] == 'recv' && $v['count']>0){
        switch($d['countinfo'][$v['count']]){
          case 'restart':
            $restart++;
            $title="<<restart count={$v['count']}>>";
            break;
          case 'esi':
            $title='<<ESI request>>';
            break;
        }
      }

//////////
      foreach($v['trace'] as $kk => $vv){
        $tmpm[] = $this->_trace2array($vv);
      }
//      $tmpm[] = array('k' => '','v' => '');
      if($v['method'] == 'hash' && isset($d['hash'][$v['count']])){
        $y = '';
        $z = '';
        foreach($d['hash'][$v['count']] as $x){
          $y .= $z.$x;
          $z = ' + ';
        }
        $tmpm[] = array('k' => 'hash','v' => $y);
      }

      $tmpm[] = array('k' => 'return','v' => $v['return']);
      $tmp[]=array('key'=>$v['method'],'value'=>$tmpm,'title'=>$title);
    }
    $this->_echoRect($tmp);
    echo "\n\n";

//////////////////////////
    //ヘッダリスト

    foreach($d['var'] as $k => $v){
      $tmp = array();
      $tmp[] = array(
        'k' => 'variable infomation.',
        'v' => null
      );
      $tmp[] = array(
        'k' => ' ',
        'v' => ' '
      );
      $this->_echoline('#');
      if($k>0){
        switch($d['countinfo'][$k]){
          case 'restart':
            $restart++;
            $tmp[] = array(
              'k' => 'restart',
              'v' => "yes(count={$k})"
            );
            break;
          case 'esi':
            $tmp[] = array(
              'k' => 'ESI',
              'v' => "yes"
            );
          
        }
      }
      if(isset($d['hash'][$k])){
        $y = '';
        $z = '';
        foreach($d['hash'][$k] as $x){
          $y .= $z.$x;
          $z =' + ';
        }
        $tmp[] = array(
          'k' => 'hash',
          'v' => $y
        );

      }
      $this->_echo($tmp);
      foreach($v as $kk => $vv){
        $tmp = array();
        foreach($vv as $kkk => $vvv){
          foreach($vvv as $kkkk => $vvvv){

            if($kkk == 'http'){
              $vd = explode(': ',$vvvv);
              if(isset($vd[1])){
                $tmp[] = array(
                  'k' => $kk.'.'.$kkk.'.'.$vd[0],
                  'v' => $vd[1]
                );
              }else{
                $tmp[] = array(
                  'k' => $kk.'.'.$kkk.'.'.$vd[0],
                  'v' => ''
                );
              }
            }else{
              $tmp[] = array(
                'k' => $kk.'.'.$kkk,
                'v' => $vvvv
              );
            }
          }
        }
        $this->_echoline();
        $this->_echo($tmp);
        echo "\n";
        
      }
    }
    $this->_echoline('#');
    $this->_echoline('#');
    $this->_echoline('#');
  }

/////////////////////////////////////////////////////
  public function clearRetData(){
    $this->retData = array();
    $this->tmpTrx  = array();
  }

/////////////////////////////////////////////////////
  public function addTrx($raw){
    if(!$raw) return false;
    $n    = $raw['trx'];
    $tag  = $raw['tag'];
    if(!isset($this->tmpTrx[$n]) && ($tag == 'ReqStart' || $tag == 'BackendOpen'))
      $this->tmpTrx[$n] = array();
    if(!isset($this->tmpTrx[$n]))
      return false;
    $sess = &$this->tmpTrx[$n];
    $t = explode(' ',$raw['msg']);

    if(count($sess)>0)
      $req = &$sess[count($sess)-1];
      
    switch($raw['tag']){
      //バックエンド系
      case 'BackendOpen':
        $sess[] = array(
          'count'      =>0,
          'countinfo'  =>array(),
          'info'       =>array(),
          'var'        =>array(),
          'raw'        =>array(),
        );
        $req = &$sess[count($sess)-1];
        $req['info']['backend.name']   =$t[0];
        $req['info']['backend.server'] =$t[3].':'.$t[4];
        break;
      case 'BackendClose':
        break;
      //リクエスト系
      case 'ReqStart':
        $sess[] = array(
          'count'      =>0,
          'restartflg' =>false,
          'recvcount'  =>0,
          'countinfo'  =>array(),
          'info'       =>array(),
          'backend'    =>array(),
          'var'        =>array(),
          'call'       =>array(),
          'raw'        =>array(),
          'hash'       =>array(),
        );
        unset($req);
        $req = &$sess[count($sess)-1];
        $req['var'][0]['client']['ip']   = array($t[0]);
        break;
      case 'Length':
        $req['info']['length'][] = $raw['msg'];
        break;
      case 'ReqEnd':
        //コミット
        if($t[3] != 'nan' && $t[3]>0)
          $req['info']['time.accept']  =$t[3];
        if($t[4] != 'nan' && $t[4]>0)
          $req['info']['time.execute'] =$t[4];
        if($t[5] != 'nan' && $t[5]>0)
          $req['info']['time.exit']    =$t[5];

        $req['info']['time.total']     =$t[2]-$t[1];

        $this->retData[] = $req;
        unset($sess[count($sess)-1]);
        if(count($sess) == 0)
          unset($sess);
        return true;
        break;
      case 'Backend':
        $btmp = array_shift($this->tmpTrx[$t[0]]);
        $req['backend'][] = $btmp;
        foreach($btmp['var'][0] as $k => $v){
          $req['var'][$req['count']][$k] = $v;
        }
        break;
      case 'VCL_call':
        $call_cnt=count($t);
        $ret = $t[$call_cnt - 1];
        $method = $t[0];
        if($method == 'recv'){
          if($req['restartflg']){
            $req['restartflg'] = false;
          }else{
            $req['recvcount']++;
            if($req['recvcount']>1){
              $req['count']++;
              $req['countinfo'][$req['count']] = 'esi';
            }
          }
        }
        $tmp = array();
        $tmp['method']      = $method;
        $tmp['count']       = $req['count'];
        $tmp['trace']       = array();
        if($call_cnt>2){
          //12 VCL_call     c recv 1 8.1 3 16.1 8 41.5 9 42.9 11 46.13 12 49.5 14 59.5 16 63.5 18 67.5 lookup
          $cmax           = $call_cnt-1;
          $vrtcount       = 0;
          $vrtline        = 0;
          $vrtpos         = 0;
          if(!isset($tmp['trace']))
            $tmp['trace'] = array();
          for($x=1 ; $x < $cmax ; $x++){
            if(0!=($x % 2)){
              $vrtcount = $t[$x];
            }else{
              $tt = explode('.',$t[$x]);
              $trace              = array();
              $trace['type']      = 'trace';
              $trace['vrt_count'] = $vrtcount;
              $trace['vcl_line']  = $tt[0];
              $trace['vcl_pos']   = $tt[1];
              $trace['count']     = $req['count'];
              $tmp['trace'][]     = $trace;
            }
          }
/*
          $tt                 = explode('.',$t[2]);
          $tmp['trace'][]     = array();
          $trace              = &$tmp['trace'][0];
          $trace['type']      = 'trace';
          $trace['vrt_count'] = $t[1];
          $trace['vcl_line']  = $tt[0];
          $trace['vcl_pos']   = $tt[1];
          $trace['count']     = $req['count'];
*/
        }

        $tmp['return']  =$ret;
        if($ret == 'restart'){
          $req['count']++;
          $req['restartflg'] = true;
          $req['countinfo'][$req['count']] = 'restart';
        }

        $req['call'][]    =$tmp;
        break;
      case 'VCL_return':
        $req['call'][count($req['call'])-1]['return']  =$t[0];
        break;
      case 'VCL_trace':
        $tt = explode('.',$t[1]);
        $req['call'][count($req['call'])-1]['trace'][] = array(
          'type'    =>'trace',
          'vrt_count' =>$t[0],
          'vcl_line'  =>$tt[0],
          'vcl_pos'   =>$tt[1],
          'count'     =>$req['count'],
        );
        break;
      case 'VCL_Log':
        $req['call'][count($req['call'])-1]['trace'][] = array(
          'type'    =>'log',
          'data'    =>$raw['msg'],
        );
        break;
      case 'Hash':
        $req['hash'][$req['count']][] = $raw['msg'];
        break;
    }


    //var
    if(!is_null($raw['varkey'])){
      $req['var'][$req['count']][$raw['var']][$raw['varkey']][] = $raw['msg'];

    }
  }


  //1行解釈
/////////////////////////////////////////////////////
  public function rawDecode($s){
    //形式チェック
    if(!preg_match('/ +([^ ]+) +([^ ]+) +([^ ]+) +(.*)/',$s,$m)) return false;

    //テンポラリ代入
    $tmp = array();
    $tmp['trx']    =$m[1];
    $tmp['tag']    =$m[2];
    $tmp['tg']     =$m[3];
    $tmp['msg']    =$m[4];
    $tmp['var']    =null;
    $tmp['varkey'] =null;

    if($tmp['trx'] == '0'){return false;}
    //変数の場合特定する
    $rxtx  =substr($tmp['tag'],0,2);
    if($rxtx == 'Tx' || $rxtx == 'Rx' || $rxtx == 'Ob'){
      $tmpk = '';
      foreach($this->tagVar as $k => $v){
        if(false !== strpos($tmp['tg'] . $tmp['tag'],$k)){
          $tmp['var'] = $v;
          $tmpk = substr($tmp['tag'],strlen($k)-1);
          if(array_key_exists($tmpk,$this->tagRep)){
            $tmp['varkey'] = $this->tagRep[$tmpk];
          }else{
            $tmp['varkey'] = $tmpk;
          }
          break;
        }
      }

    }
    return $tmp;
  }
  
  
  
  
}






main();
