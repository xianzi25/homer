<?php
/*
 * HOMER Web Interface
 * Homer's PCAP generator Class
 *
 * Copyright (C) 2011-2012 Alexandr Dubovikov <alexandr.dubovikov@gmail.com>
 * Copyright (C) 2011-2012 Lorenzo Mangani <lorenzo.mangani@gmail.com>
 *
 * The Initial Developers of the Original Code are
 *
 * Alexandr Dubovikov <alexandr.dubovikov@gmail.com>
 * Lorenzo Mangani <lorenzo.mangani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
*/

define(_HOMEREXEC, "1");

/* MAIN CLASS modules */
include("class/index.php");

define(_HOMEREXEC, "1");


class pcap_hdr {
   public $magic; 	// 4 bits
   public $version_major; //2
   public $version_minor; //2
   public $thiszone; //4
   public $sigfigs;  //4
   public $snaplen;  //4
   public $network; //4
}

class pcaprec_hdr {
    public $ts_sec;  //4
    public $ts_usec; //4
    public $incl_len; //4
    public $orig_len; //4
}

// ethernet packet header - 14 bytes
class ethernet_header {
	public $dest_mac; 	// 48 bit 
	public $src_mac;	// 48 bit 
	public $type;	// 16 bit 
}

class ipv4_packet {
	public $ver_len;	// 4 bits
	public $tos;		// 8 bits
	public $total_len;	// 16 bits
	public $ident;		// 16 bits
	public $fl_fr;		// 4 bits
	public $ttl;		// 8 bits
	public $proto;		// 8 bits
	public $checksum;	// 16 bits
	public $src_ip;		// 32 bits
	public $dst_ip;		// 32 bits
	public $options;
}

class udp_header {
       public $src_port; //16 bits
       public $dst_port;  //16 bits
       public $length;    //16 bits
       public $checksum;  //16 bits
}

class size_hdr {
      public $ethernet=14;
      public $ip = 20;
      public $ip6 = 20;
      public $udp = 8;
      public $tcp = 20;
      public $data = 0;
      public $total = 0;
}

function checksum($data) 
{ 
    if( strlen($data)%2 ) $data .= "\x00";      
    $bit = unpack('n*', $data); 
    $sum = array_sum($bit);      
    while ($sum >> 16) 
        $sum = ($sum >> 16) + ($sum & 0xffff); 
   $sum = ~$sum;
   $sum = $sum & 0xffff;
   return $sum;
} 

$size = new size_hdr();

//Write PCAP HEADER 
$pcaphdr = new pcap_hdr();
$pcaphdr->magic = 2712847316;
$pcaphdr->version_major = 2;
$pcaphdr->version_minor = 4;
$pcaphdr->thiszone = 0;
$pcaphdr->sigfigs = 0;
$pcaphdr->snaplen = 102400;
$pcaphdr->network = 1;

$buf="";
$pcap_packet = pack("lssllll", $pcaphdr->magic, $pcaphdr->version_major, 
            $pcaphdr->version_minor, $pcaphdr->thiszone, 
            $pcaphdr->sigfigs, $pcaphdr->snaplen, $pcaphdr->network);

$buf=$pcap_packet;

//Ethernet header
$eth_hdr = new ethernet_header();
$eth_hdr->dest_mac = "020202020202";
$eth_hdr->src_mac = "010101010101";
$eth_hdr->type = "0800";
$ethernet = pack("H12H12H4", $eth_hdr->dest_mac, $eth_hdr->src_mac, $eth_hdr->type);


//Temporally tnode == 1
$tnode=1;
$option = array(); //prevent problems

// Check if Table is set
if ($table == NULL) { $table="sip_capture"; }

//$cid="1234567890";

// Get Variables
$cid = getVar('cid', NULL, 'get', 'string');
$b2b = getVar('b2b', NULL, 'get', 'string');
$from_user = getVar('from_user', NULL, 'get', 'string');
$to_user = getVar('to_user', NULL, 'get', 'string');
$limit = getVar('limit', NULL, 'get', 'string');
// Get time & date if available
$flow_from_date = getVar('from_date', NULL, 'get', 'string');
$flow_to_date = getVar('to_date', NULL, 'get', 'string');
$flow_from_time = getVar('from_time', NULL, 'get', 'string');
$flow_to_time = getVar('to_time', NULL, 'get', 'string');

if(BLEGDETECT == 1) $b2b = 1;

if (isset($flow_to_date, $flow_from_time, $flow_to_time))
{
	$ft = date("Y-m-d H:i:s", strtotime($flow_from_date." ".$flow_from_time));
	$tt = date("Y-m-d H:i:s", strtotime($flow_to_date." ".$flow_to_time));
	$where = "( `date` BETWEEN '$ft' AND '$tt' )";
}

/* Prevent break SQL */
if(isset($where)) $where.=" AND ";

// Build Search Query
if(isset($cid)) {

	/* CID */
	$where .= "( callid = '".$cid."'";
	/* Detect second B-LEG ID */
	if($b2b) {
	    if(BLEGCID == "x-cid") {
        	  $query = "SELECT callid FROM ".HOMER_TABLE." WHERE ".$where." AND callid_aleg='".$cid."'";
	          $cid_aleg = $db->loadResult($query);
	    }
	    else if (BLEGCID == "-0") $cid_aleg = $cid.BLEGCID;
	    else $cid_aleg = $cid;

	    $where .= " OR callid='".$cid.BLEGCID."'";
	}
	$where .= ") ";
	         
} else if(isset($from_user)) {
         $where .= "( from_user = '".$from_user."'";
         if(isset($to_user)) { $where .= " OR to_user='".$to_user."')"; } else {  $where .= ") ";}
} else if(isset($to_user)) {
         $where .= "( to_user = '".$to_user."')";
}

if(!isset($limit)) { $limit = 100; }

$localdata=array();

//if($db->dbconnect_homer($mynodeshost[$tnode])) {
if(!$db->dbconnect_homer(NULL))
{
    //No connect;
    exit;
}


$query = "SELECT * "
	."\n FROM ".HOMER_TABLE
        ."\n WHERE ".$where." order by micro_ts ASC limit ".$limit;
$rows = $db->loadObjectList($query);

//$query="SELECT * FROM $table WHERE $where order by micro_ts limit 100;";
$rows = $db->loadObjectList($query);
foreach($rows as $row) {

	$data=$row->msg;
	$size->data=strlen($data);

	//Ethernet + IP + UDP
	$size->total=$size->ethernet + $size->ip + $size->udp;
	//+Data
	$size->total+=$size->data;

	//Pcap record
	$pcaprec_hdr = new pcaprec_hdr();
	$pcaprec_hdr->ts_sec = intval($row->micro_ts / 1000000);  //4
	$pcaprec_hdr->ts_usec = $row->micro_ts - ($pcaprec_hdr->ts_sec*1000000); //4   
	$pcaprec_hdr->incl_len = $size->total; //4
	$pcaprec_hdr->orig_len = $size->total; //4

	$pcaprec_packet = pack("llll", $pcaprec_hdr->ts_sec, $pcaprec_hdr->ts_usec, 
                       $pcaprec_hdr->incl_len, $pcaprec_hdr->orig_len);

	$buf.=$pcaprec_packet;

	//ethernet header
	$buf.=$ethernet;

	//UDP
	$udp_hdr = new udp_header();
	$udp_hdr->src_port = $row->source_port;
	$udp_hdr->dst_port = $row->destination_port;
	$udp_hdr->length = $size->udp + $size->data; 
	$udp_hdr->checksum = 0;

	//Calculate UDP checksum
	$pseudo = pack("nnnna*", $udp_hdr->src_port,$udp_hdr->dst_port, $udp_hdr->length, $udp_hdr->checksum, $data);
	$udp_hdr->checksum = &checksum($pseudo);

	//IPHEADER

	$ipv4_hdr = new ipv4_packet();

	$ip_ver = 4;
	$ip_len = 5;
	$ip_frag_flag = "010";
	$ip_frag_oset = "0000000000000";
	$ipv4_hdr->ver_len = $ip_ver . $ip_len;
	$ipv4_hdr->tos = "00";
	$ipv4_hdr->total_len = $size->ip + $size->udp + $size->data;;
	$ipv4_hdr->ident = 19245;
	$ipv4_hdr->fl_fr = 4000;
	$ipv4_hdr->ttl = 30;
	$ipv4_hdr->proto = 17;
	$ipv4_hdr->checksum = 0;
	$ipv4_hdr->src_ip = sprintf("%u", ip2long($row->source_ip));
	$ipv4_hdr->dst_ip = sprintf("%u", ip2long($row->destination_ip));

	$pseudo = pack('H2H2nnH4C2nNN', $ipv4_hdr->ver_len,$ipv4_hdr->tos,$ipv4_hdr->total_len, $ipv4_hdr->ident,
	            $ipv4_hdr->fl_fr, $ipv4_hdr->ttl,$ipv4_hdr->proto,$ipv4_hdr->checksum, $ipv4_hdr->src_ip, $ipv4_hdr->dst_ip);

	$ipv4_hdr->checksum = checksum($pseudo);

	$pkt = pack('H2H2nnH4C2nNNnnnna*', $ipv4_hdr->ver_len,$ipv4_hdr->tos,$ipv4_hdr->total_len, $ipv4_hdr->ident,
        	    $ipv4_hdr->fl_fr, $ipv4_hdr->ttl,$ipv4_hdr->proto,$ipv4_hdr->checksum, $ipv4_hdr->src_ip, $ipv4_hdr->dst_ip,
	            $udp_hdr->src_port,$udp_hdr->dst_port, $udp_hdr->length, $udp_hdr->checksum, $data);

	//IP/UDP and DATA header	
	$buf.=$pkt;
}


$pcapfile="HOMER_$cid.pcap";
$fsize=strlen($buf);;
header("Content-type: application/octet-stream");
header("Content-Disposition: filename=\"".$pcapfile."\"");
header("Content-length: $fsize");
header("Cache-control: private"); 
echo $buf;
exit;




?>
