<?php

function send_to_gateway($str)
{
  $ip = "255.255.255.255";
  $port = 8003;
  $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP); 
  socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
  socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>3, "usec"=>0));
  socket_sendto($sock, $str, strlen($str), 0, $ip, $port);
  $count = 0;
  while(true) {
    $ret = @socket_recvfrom($sock, $buf, 186, 0, $ip, $port);
    if ($ret === false) break;
    if (strlen($buf)==186) { $result[$count]=$buf; $count++; }
  }
  socket_close($sock);
  return $result;
}

function query_gateways($deviceid)
{
  if ($deviceid=="") {
    $command = chr(1);
    $deviceid = "000000000000";
  } else {
    $command = chr(2);
  }
  $ip = "255.255.255.255";
  $port = 8003;
  $str = chr(0).$command.hex2bin($deviceid).chr(0).chr(10);
  return send_to_gateway($str);
}

/*
# # # # # # # # # #   P R O X Y   # # # # # # # # # #
*/
if (isset($_GET['url']))
{
  file_put_contents('export.txt', print_r($_GET, true));
  exit;
}

/*
# # # # # # # # # #   set Gateway proxy to Home Assistant   # # # # # # # # # #
*/
if (isset($_POST['setproxy']))
{
  $result = query_gateways($_POST['setproxy']);
  if (count($result)==1) { $buf=chr(0).chr(4).substr($result[0],2,6).chr(0).chr(181).substr($result[0],15,171); }
  $proxy_str = "homeassistant.local";
  if ($buf !== "")
  {
    $buf[109] = chr(1);
    for ($i=110; $i<=174; $i++) { $buf[$i]=chr(0); }
    $buf[175]=chr(0x1F); $buf[176]=chr(0x43);
    for ($i=1; $i<=strlen($proxy_str); $i++ ) { $buf[109+$i]=$proxy_str[$i-1]; }
    send_to_gateway($buf);
  }
  exit;
}

/*
# # # # # # # # # #   reset Gateway proxy to 192.168.1.1   # # # # # # # # # #
*/
if (isset($_POST['resetproxy']))
{
  $result = query_gateways($_POST['resetproxy']);
  if (count($result)==1) { $buf=chr(0).chr(4).substr($result[0],2,6).chr(0).chr(181).substr($result[0],15,171); }
  $proxy_str = "192.168.0.1";
  if ($buf !== "")
  {
    $buf[109] = chr(0);
    for ($i=110; $i<=174; $i++) { $buf[$i]=chr(0); }
    $buf[175]=chr(0x1F); $buf[176]=chr(0x43);
    for ($i=1; $i<=strlen($proxy_str); $i++ ) { $buf[109+$i]=$proxy_str[$i-1]; }
    send_to_gateway($buf);
  }
  exit;
}

/*
# # # # # # # # # #   write config of selected gateway   # # # # # # # # # #
*/
if (isset($_POST['setconfig']))
{
  $buf = chr(0).chr(4).hex2bin($_POST['setconfig']).chr(0).chr(181);
  if ($_POST['dhcp']=='yes') { $buf.=chr(1); } else { $buf.=chr(0); }
  $buf .= hex2bin(str_pad(dechex(ip2long($_POST['ip'])), 8, "0", STR_PAD_LEFT)); 
  $buf .= hex2bin(str_pad(dechex(ip2long($_POST['netmask'])), 8, "0", STR_PAD_LEFT)); 
  $buf .= hex2bin(str_pad(dechex(ip2long($_POST['gateway'])), 8, "0", STR_PAD_LEFT));
  for ($i=0;$i<21;$i++) { $buf.=chr(0); }
  for ($i=0;$i<strlen($_POST['devicename']);$i++) { $buf[23+$i]=$_POST['devicename'][$i]; }
  for ($i=0;$i<65;$i++) { $buf.=chr(0); }
  for ($i=0;$i<strlen($_POST['servername']);$i++) { $buf[44+$i]=$_POST['servername'][$i]; }
  if ($_POST['proxyset']=='yes') { $buf.=chr(1); } else { $buf.=chr(0); }
  for ($i=0;$i<65;$i++) { $buf.=chr(0); }
  for ($i=0;$i<strlen($_POST['proxy']);$i++) { $buf[110+$i]=$_POST['proxy'][$i]; }
  $buf .= hex2bin(str_pad(dechex($_POST['port']), 4, "0", STR_PAD_LEFT));
  $buf .= hex2bin(str_pad(dechex(ip2long($_POST['dns'])), 8, "0", STR_PAD_LEFT));
  send_to_gateway($buf);
  exit;
}

/*
# # # # # # # # # #   read and display config of selected gateway   # # # # # # # # # #
*/
if (isset($_GET['config']))
{
  $result = query_gateways($_GET['config']);
  if (count($result)==1) { 
    $devicename = trim(substr($result[0],28,21));
    $servername = trim(substr($result[0],49,65));
    $ip = long2ip(unpack("N",substr($result[0],16,4))[1]);
    $netmask = long2ip(unpack("N",substr($result[0],20,4))[1]);
    $gate = long2ip(unpack("N",substr($result[0],24,4))[1]);
    $dns = long2ip(unpack("N",substr($result[0],182,4))[1]);
    $address = strtoupper(bin2hex(substr($result[0],2,6)));
    $proxy = trim(substr($result[0],115,65));
    $port = unpack("n",substr($result[0],180,2))[1];
    if ($result[0][15]==chr(1)) { $dhcp="selected"; $dhcps=""; $statip=""; } else { $dhcp=""; $dhcps="show"; $statip="selected"; }
    if ($result[0][114]==chr(0)) { $noprox="selected"; $proxs=""; $prox=""; } else { $noprox=""; $proxs="show"; $prox="selected"; }
  }
  if ($_GET['config']==$address) 
  {
    $output= <<<EOD
      <form>
        <input type="hidden" id="deviceid" value="$address">
        <div class="form-row">
          <div class="form-group col-md-3"><label for="inputDevice">Device name :</label></div>
          <div class="form-group col-md-9"><input type="text" class="form-control" id="inputDevice" maxlength="20" value="$devicename"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-3"><label for="inputServer">Server name :</label></div>
          <div class="form-group col-md-9"><input type="text" class="form-control" id="inputServer" maxlength="64" value="$servername"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-12">
            <select id="dhcp" class="form-control"><option value="yes" $dhcp>Use DHCP</option><option value="no" $statip>Use static IP</option></select>
          </div>
        </div>
        <div class="fixedip collapse $dhcps" id="fixedip">
          <div class="form-row">
            <div class="form-group col-md-3"><label for="inputIP">IP address :</label></div>
            <div class="form-group col-md-9"><input type="text" class="form-control" id="inputIP" value="$ip"></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-3"><label for="inputNetmask">Netmask :</label></div>
            <div class="form-group col-md-9"><input type="text" class="form-control" id="inputNetmask" value="$netmask"></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-3"><label for="inputGateway">Gateway IP :</label></div>
            <div class="form-group col-md-9"><input type="text" class="form-control" id="inputGateway" value="$gate"></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-3"><label for="inputDNS">DNS IP :</label></div>
            <div class="form-group col-md-9"><input type="text" class="form-control" id="inputDNS" value="$dns"></div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-12">
            <select id="proxy" class="form-control"><option value="no" $noprox>Do not use proxy server</option><option value="yes" $prox>Use proxy server</option></select>
          </div>
        </div>
        <div class="fixedip collapse $proxs" id="useproxy">
          <div class="form-row">
            <div class="form-group col-md-3"><label for="inputProxy">Proxy server :</label></div>
            <div class="form-group col-md-9"><input type="text" class="form-control" id="inputProxy" maxlength="64" value="$proxy"></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-3"><label for="inputPort">Proxy port :</label></div>
            <div class="form-group col-md-9"><input type="text" class="form-control" id="inputPort" value="$port"></div>
          </div>
        </div>
      </form>
EOD;
  } else {
    $output = "<p style=\"margin-top:80px\"><center><strong>Gateway not found</strong> - check your network and try again</center></p>";
  }
  echo $output;
  exit;
}

/*
# # # # # # # # # #   search for gateways   # # # # # # # # # #
*/
if (isset($_GET['search']))
{ 
  $result=query_gateways("");
  $output = <<<EOD
    <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead>
        <tr>
          <th scope="col" class="align-middle text-left">Name and ID</th>
          <th scope="col" class="align-middle text-center">IP address</th>
          <th scope="col" class="align-middle text-right">Functions</th>
        </tr>
      </thead>
      <tbody>
EOD;
  for ($i=0;$i<count($result);$i++) {
    $buf = $result[$i];
    if (strlen($buf)==186) { 
      if ($buf[15]==0x00) { $dhcp="(DHCP)"; } else { $dhcp="(fixed)"; }
      $devicename = trim(substr($buf,28,21));
      $address = strtoupper(bin2hex(substr($buf,2,6)));
      $macaddress = preg_replace('~(..)(?!$)\.?~', '\1:', $address);
      $proxy = trim(substr($buf,115,65));
      $ip = long2ip(unpack("N",substr($result[0],11,4))[1]);
      $output .= <<<EOD
        <tr>
          <td class="align-middle text-left">$devicename [$macaddress]</td>
          <td class="align-middle text-center">$ip $dhcp</td>
          <td class="align-middle text-right">
            <button type="button" class="btn btn-primary cogs" id="$address"><i class="fa fa-cogs"></i> Settings</button>
EOD;
      if ($buf[114]==chr(1) && $proxy=="homeassistant.local") {
        $output .="            <button type=\"button\" class=\"btn btn-danger resetproxy\" id=\"$address\"><i class=\"fa fa-share-square-o\"></i> Reset proxy</button>";
      } else {
        $output .="            <button type=\"button\" class=\"btn btn-primary setproxy\" id=\"$address\"><i class=\"fa fa-check-square-o\"></i> Set proxy</button>";
      }
      $output .= <<<EOD
          </td>
        </tr>
EOD;
    }
    $output .= <<<EOD
      </tbody>
    </table>
    </div>
EOD;
  }
  if (count($result)>0) { echo $output; } else { echo "<center><strong>No gateways found on local network</strong> - check your network and try again</center>"; }
  exit;
}

/*



# # # # # # # # # #   main HTML   # # # # # # # # # #



*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Mobile-Alerts Gateway Assistant</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" type="text/css" rel="stylesheet">
<style>
  body { margin-top: 10px; overflow-y: scroll; }
  body::-webkit-scrollbar-track {	background-color: #F5F5F5; }
  body::-webkit-scrollbar { width: 12px; background-color: #F5F5F5; }
  body::-webkit-scrollbar-thumb { border-radius: 10px; -webkit-box-shadow: inset 0 0 6px rgba(0,0,0,.3); background-color: #CCC; }	
  table { margin-bottom: 0px !important; }
  th { border-top: 0px !important; border-bottom: 1px !important; }
  ul { margin-bottom: 0px !important; }
  label { padding-top: 5px; }
  .modal-header, .modal-footer { background-color: rgba(0,0,0,.03); }
  .modal-body { min-height: 248px; }
  #hass { width: 80px; }
  .fa { padding-right: 8px; }
  .card-body { padding: 0.5rem; }
  .spinner-border { margin: 2.4rem; }
  button { margin: 2px 0px; }
</style>
</head>

<body>
<div class="container">
  <div class="row mb-3">
  <div class="col-md-12">
    <div class="card shadow-sm">
      <div class="card-header"><h4>Welcome to Mobile-Alerts Gateway Assistant</h4></div>
      <div class="card-body">
        <p class="card-text">This is the configuration frontend of Mobile-Alerts Gateway for Home Assistant.</p>	
        <div class="row">
          <div class="col-md-12"> 
            <u>To Do:</u><br>
            <ul>
              <li>capture network traffic from Gateway</li>
              <li>relay traffic to original server (www.data199.com)</li>
              <li>send data to MQTT server</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
  </div>

  <div class="row mb-3">
  <div class="col-md-12">
    <div class="card shadow-sm">
      <div class="card-header container-fluid">
        <div class="row">
          <div class="col-6 col-md-8"><h4>Gateways</h4></div>
          <div class="col-6 col-md-4 text-right"><button type="button" class="btn btn-primary search" style="display:none;"><i class="fa fa-refresh"></i> Refresh</button></div>
        </div>
      </div>
      <div class="card-body">
        <div class="search-body align-middle text-center">Press the "<strong>Search local network</strong>" button to list available gateways</div>	
      </div>
    </div>
  </div>
  </div>

  <div class="row mb-3">
  <div class="col-md-12">
    <div class="card shadow-sm" style="background-color: rgba(0,0,0,0.03);"><div class="card-body text-center">
      Thanks for <a href="https://github.com/sarnau/MMMMobileAlerts" target="_blank"><strong>sarnau</strong></a> for the documentation of Mobile-Alerts Gateway.
    </div></div>
  </div>
  </div>

</div>

<div class="modal" id="devicedata" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header align-middle"><h5 class="modal-title">Gateway settings</h5></div>
      <div class="modal-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary save" style="display:none;"><i class="fa fa-floppy-o"></i> Save changes</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-close"></i> Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>

<script>
function isValid(text) {
    if (text == null || text == "") return false;
    if (text.match("^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$") != null) return true;
}

$(document).ready(function(){
  $(".search").click(function(){
    $(".search-body").html("<center><div class=\"spinner-border\" role=\"status\"></div></center>");
    $.get("<?=basename(__FILE__)?>?search", function(data){ $(".search-body").html(data); $(".search").show(); });
  });

  $(document).on('click','.cogs',function(){
    $('.save').hide();
    $(".modal-body").html("<div class=\"text-center\"><div class=\"spinner-border\" role=\"status\" style=\"margin-top:80px\"></div></div>");
    $("#devicedata").modal('show');
    $.get("<?=basename(__FILE__)?>?config="+$(this).attr('id'), function(data){ $(".modal-body").html(data); if (typeof $('#deviceid').val() !== 'undefined') { $('.save').show(); } });
    $(".modal-header").html("<h5 class=\"modal-title\">Gateway settings</h5><h5 class=\"modal-title float-right\">["+$(this).attr('id').replace(/(.{2})/g, "$1:").substring(0,17)+"]</h5>"); 
  });

  $(document).on('change','#dhcp',function() {
    opt = $(this).val();
    if (opt=="no") { $('#fixedip').collapse("show"); } else if (opt == "yes") { $('#fixedip').collapse("hide"); }
  });

  $(document).on('change','#proxy',function() {
    opt = $(this).val();
    if (opt=="yes") { $('#useproxy').collapse("show"); } else if (opt == "no") { $('#useproxy').collapse("hide"); }
  });

  $(document).on('keyup','#inputIP',function() { 
    if (isValid($('#inputIP').val()) ) { $('#inputIP').css("border","1px solid #ced4da"); } else { $('#inputIP').css("border","3px solid red"); }
  });

  $(document).on('keyup','#inputNetmask',function() { 
    if (isValid($('#inputNetmask').val()) ) { $('#inputNetmask').css("border","1px solid #ced4da"); } else { $('#inputNetmask').css("border","3px solid red"); }
  });

  $(document).on('keyup','#inputGateway',function() { 
    if (isValid($('#inputGateway').val()) ) { $('#inputGateway').css("border","1px solid #ced4da"); } else { $('#inputGateway').css("border","3px solid red"); }
  });

  $(document).on('keyup','#inputDNS',function() { 
    if (isValid($('#inputDNS').val()) ) { $('#inputDNS').css("border","1px solid #ced4da"); } else { $('#inputDNS').css("border","3px solid red"); }
  });

  $(document).on('keyup','#inputPort',function() { 
    if ($.isNumeric($('#inputPort').val()) ) { $('#inputPort').css("border","1px solid #ced4da"); } else { $('#inputPort').css("border","3px solid red"); }
  });

  $(document).on('click','.save',function() {
    if (typeof $('#deviceid').val() !== 'undefined')
    {
      var count = 0;
      if (! isValid($('#inputIP').val()) ) { $('#inputIP').css("border","3px solid red"); count++; }
      if (! isValid($('#inputNetmask').val()) ) { $('#inputNetmask').css("border","3px solid red"); count++; }
      if (! isValid($('#inputGateway').val()) ) { $('#inputGateway').css("border","3px solid red"); count++; }
      if (! isValid($('#inputDNS').val()) ) { $('#inputDNS').css("border","3px solid red"); count++; }
      if (! $.isNumeric($('#inputPort').val()) ) { $('#inputPort').css("border","3px solid red"); count++; }
      if (count==0) {
        $("#devicedata").modal('hide');
        $(".search-body").html("<center><div class=\"spinner-border\" role=\"status\"></div></center>"); 
        $.post('<?=basename(__FILE__)?>',
        {
          setconfig: $('#deviceid').val(),
          devicename: $('#inputDevice').val(),
          servername: $('#inputServer').val(),
          dhcp: $('#dhcp').val(),
          ip: $('#inputIP').val(),
          netmask: $('#inputNetmask').val(),
          gateway: $('#inputGateway').val(),
          dns: $('#inputDNS').val(),
          proxyset: $('#proxy').val(),
          proxy: $('#inputProxy').val(),
          port: $('#inputPort').val()
        }, function(data, status) { setTimeout(function() { $( ".search" ).click() }, 3000); });
      }
    }
  });

  $(document).on('click','.setproxy',function() {
    $(".search-body").html("<center><div class=\"spinner-border\" role=\"status\"></div></center>");
    if (typeof $(this).attr('id') !== 'undefined')
    {
      $.post('<?=basename(__FILE__)?>', { setproxy: $(this).attr('id'), }, 
        function(data, status) { setTimeout(function() { $( ".search" ).click() }, 3000); });
    }
  });

  $(document).on('click','.resetproxy',function() {
    $(".search-body").html("<center><div class=\"spinner-border\" role=\"status\"></div></center>");
    if (typeof $(this).attr('id') !== 'undefined')
    {
      $.post('<?=basename(__FILE__)?>', { resetproxy: $(this).attr('id'), }, 
        function(data, status) { setTimeout(function() { $( ".search" ).click() }, 3000); });
    }
  });

  $(".search").click();
});
</script>
<?php echo file_get_contents('export.txt'); ?>
</body>
</html>