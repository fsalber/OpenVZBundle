<?php
/**
 * Distributed under MIT License,
 * Symfony bundle for managed OpenVZ Containers from Applications.
 * @author Florian SALBER <florian.salber@gmail.com>
 */
namespace FSALBER\OpenVZBundle;

class OpenVZApi {

    /**
     * Contains SSH tunnel
     */
    protected static $ssh;

    /**
     * @param $host hostname
     * @param $user username
     * @param $pass user password
     * @param $port ssh port if not "22"
     * @throws Exception
     */
    public function __construct($host, $user, $pass, $port){
        $connection = \ssh2_connect($host, $port);
        ssh2_auth_password( $connection, $user, $pass );

        if(!$connection->ssh2_exec('uptime')) {
            throw new Exception('SSH connection not provided');
        }

        $this->ssh = $connection;
    }

    /**
     * Allow to create VMs from params object which contains VMs default informations.
     * @param object $params
     * @return mixed
     */
    public static function create($params){
        $commands = "/usr/bin/sudo /usr/sbin/vzctl create {$params->ctid} --ostemplate {$params->template} --hostname {$params->hostname}
            /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --diskspace {$params->disk}G:{$params->disk}G --save
            /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --vmguarpages {$params->ram}M --oomguarpages {$params->ram}M --privvmpages {$params->ram}M:{$params->burst}M --swap
            {$params->swap}M --save
            /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --nameserver {$params->dns} --save
            /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --userpasswd root:{$params->password} --save
            /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --cpuunits {$params->cpu_units} --save
            /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --cpulimit {$params->cpu_limit}m --cpus {$params->cpus} --save
            /usr/bin/sudo /usr/sbin/vzctl start {$params->ctid}";

        return ssh2_exec($this->ssh, $commands);
    }

    /**
     * Allow to destroy VM from Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function destroy($ctid){
        $commands = "/usr/bin/sudo /usr/sbin/vzctl stop {$ctid}
            /usr/bin/sudo /usr/sbin/vzctl destroy {$ctid}";
        return ssh2_exec($this->ssh, $commands);
    }

    /**
     * Allow to Rebuild VM (Re-install like)
     * @param object $params
     */
    public static function rebuild($params){
        if($this->estroy($params->ctid)){
            $this->reate($params);
        }
    }

    /**
     * Allow to edit CPU, RAM, Disk size of VM
     * @param object $params
     * @return string
     */
    public static function resize($params){
        $vdisk = ssh2_exec($this->ssh, "/usr/sbin/vzlist {$params->ctid} -Ho diskspace");
        if(($params->disk*1024*1024) > $vdisk){
            $commands = "/usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --ram {$params->ram}M --swap {$params->swap}M --save;
                /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --cpuunits {$params->cpuu} --save;
                /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --cpulimit {$params->cpul} --cpus {$params->cpus} --save;
                /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --diskspace {$params->disk}G:{$params->disk}G --save;
                /usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --diskinodes {$params->inodes}:{$params->inodes} --save;";
            return ssh2_exec($this->ssh, $commands);
        }
        return "New disk size cannot be less than current disk size!";
    }

    /**
     * Allow to add a IP to a particular VM
     * @param object $params
     * @return mixed
     */
    public static function addip($params){
        if(isset($params->ips) && count($params->ips) > 1) {
            $commands = "";
            foreach($params->ips as $ip){
                $commands .= "/usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --ipadd {$ip} --save";
            }
        } else {
            $commands = "/usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --ipadd {$params->ip} --save";
        }
        return ssh2_exec($this->ssh, $commands);
    }

    /**
     * Allow to remove a IP from a particular VM
     * @param object $params
     * @return mixed
     */
    public static function delip($params){
        if(isset($params->ips) && count($params->ips) > 1) {
            $commands = "";
            foreach($params->ips as $ip){
                $commands .= "/usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --ipdel {$ip} --save";
            }
        } else {
            $commands = "/usr/bin/sudo /usr/sbin/vzctl set {$params->ctid} --ipdel {$params->ip} --save";
        }
        return ssh2_exec($this->ssh, $commands);
    }

    /**
     * Start VM by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function start($ctid){
        return ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl start {$ctid}");
    }

    /**
     * Stop VM by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function stop($ctid){
        return ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl stop {$ctid}");
    }

    /**
     * Restart VM by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function vrestart($ctid){
        return ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl restart {$ctid}");
    }

    /**
     * Suspend VM by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function suspend($ctid){
        return ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl set {$ctid} --disabled yes --save; /usr/bin/sudo /usr/sbin/vzctl stop {$ctid}");
    }

    /**
     * Unsuspend VM by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function unsuspend($ctid){
        return ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl set {$ctid} --disabled no --save; /usr/bin/sudo /usr/sbin/vzctl start {$ctid}");
    }

    /**
     * Set default VM's ROOT password by Container ID (ctid)
     * @param $ctid int
     * @param $password
     * @return mixed
     */
    public static function set_password($ctid,$password){
        return ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl start {$ctid}; /usr/bin/sudo /usr/sbin/vzctl set {$ctid} --userpasswd root:{$password} --save");
    }

    /**
     * Set VM's hostname by Container ID (ctid)
     * @param $ctid int
     * @param $hostname
     * @return mixed
     */
    public static function set_hostname($ctid,$hostname){
        return ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl start {$ctid}; /usr/bin/sudo /usr/sbin/vzctl set {$ctid} --hostname {$hostname} --save");
    }

    /**
     * Set VM's DNS by Container ID (ctid)
     * @param $ctid int
     * @param $dns1
     * @param $dns2
     * @return mixed
     */
    public static function set_dns($ctid,$dns1,$dns2){
        return ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl set $ctid --nameserver {$dns1}  --nameserver {$dns2} --save;");
    }

    /**
     * Enable VM's tuntap by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function enable_tuntap($ctid){
        $commands = "modprobe tun; /usr/bin/sudo /usr/sbin/vzctl set {$ctid} --devnodes net/tun:rw --save
                /usr/bin/sudo /usr/sbin/vzctl set {$ctid} --devices c:10:200:rw --save
                /usr/bin/sudo /usr/sbin/vzctl stop {$ctid}
                /usr/bin/sudo /usr/sbin/vzctl set {$ctid} --capability net_admin:on --save
                /usr/bin/sudo /usr/sbin/vzctl start {$ctid}
                /usr/bin/sudo /usr/sbin/vzctl exec {$ctid} mkdir -p /dev/net
                /usr/bin/sudo /usr/sbin/vzctl exec {$ctid} mknod /dev/net/tun c 10 200";
        return ssh2_exec($this->ssh, $commands);
    }

    /**
     * Disable VM's tuntap by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function disable_tuntap($ctid){
        $commands = "/usr/bin/sudo /usr/sbin/vzctl set {$ctid} --devnodes net/tun:none --save
                /usr/bin/sudo /usr/sbin/vzctl set {$ctid} --devices c:10:200:none --save
                /usr/bin/sudo /usr/sbin/vzctl stop {$ctid}
                /usr/bin/sudo /usr/sbin/vzctl set {$ctid} --capability net_admin:off --save
                /usr/bin/sudo /usr/sbin/vzctl start {$ctid}";
        return ssh2_exec($this->ssh, $commands);
    }

    /**
     * Enable VM's ppp by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function enable_ppp($ctid){
        $commands = "/usr/bin/sudo /usr/sbin/vzctl stop {$ctid}
                /usr/bin/sudo /usr/sbin/vzctl set {$ctid} --features ppp:on --save
                /usr/bin/sudo /usr/sbin/vzctl start {$ctid}";
        return ssh2_exec($this->ssh, $commands);
    }

    /**
     * Disable VM's ppp by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function disable_ppp($ctid){
        $commands = "/usr/bin/sudo /usr/sbin/vzctl set {$ctid} --features ppp:off --save;
                /usr/bin/sudo /usr/sbin/vzctl stop {$ctid};
                /usr/bin/sudo /usr/sbin/vzctl start {$ctid};
                ";
        return ssh2_exec($this->ssh, $commands);
    }

    /**
     * Get VM's tuntap status by Container ID (ctid)
     * @param $ctid int
     * @return bool
     */
    public static function tuntap_status($ctid){
        if(strlen(ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl exec {$ctid} cat /dev/net/tun")) == 48){
            return true;
        }
        return false;
    }

    /**
     * Get VM's ppp status by Container ID (ctid)
     * @param $ctid int
     * @return bool
     */
    public static function ppp_status($ctid){
        if(strlen(ssh2_exec($this->ssh, "/usr/bin/sudo /usr/sbin/vzctl exec {$ctid} cat /dev/ppp")) == 41){
            return true;
        }
        return false;
    }

    /**
     * Get VM's status by Container ID (ctid)
     * @param $ctid int
     * @return mixed
     */
    public static function status($ctid){
        return ssh2_exec($this->ssh, "/usr/sbin/vzlist {$ctid} -Ho status");
    }

    public static function tc_create($params){
        if(count($params->ips) > 1){
            foreach($params->ips as $ip) {
                switch($this->p_check($ip)){
                    case 'v4':
                        $fi = "/sbin/tc filter add dev venet0 protocol ip parent 1:0 prio {$params->ctid} u32 match ip dst {$params->ip} flowid 1:{$params->bwin};";
                        $fo = "/sbin/tc filter add dev {$params->interface} protocol ip parent 1:0 prio {$params->ctid} u32 match ip dst {$params->ip} flowid 1:{$params->bwout};";
                        break;
                    case 'v6':
                        $fi = "/sbin/tc filter add dev venet0 protocol ipv6 parent 1:0 prio {$params->ctid} u32 match ipv6 dst {$params->ip} flowid 1:{$params->bwin};";
                        $fo = "/sbin/tc filter add dev {$params->interface} protocol ipv6 parent 1:0 prio {$params->ctid} u32 match ip6 dst {$params->ip} flowid 1:{$params->bwout};";
                        break;
                }
                $commands = "/sbin/tc qdisc add dev venet0 root handle 1: htb;
                    /sbin/tc class add dev venet0 parent 1: classid 1:{$params->bwin} htb rate {$params->bwin}mbit;
                    /sbin/tc qdisc add dev venet0 parent 1:{$params->bwin} handle {$params->bwin}: sfq perturb 10;
                    {$fi}
                    /sbin/tc qdisc add dev {$params->interface} root handle 1: htb;
                    /sbin/tc class add dev {$params->interface} parent 1: classid 1:{$params->bwout} htb rate {$params->bwout}mbit;
                    /sbin/tc qdisc add dev {$params->interface} parent 1:{$params->bwout} handle {$params->bwout}: sfq perturb 10;
                    {$fo}";
            }
        } else {
            switch($this->p_check($params->ip)){
                case 'v4':
                    $fi = "/sbin/tc filter add dev venet0 protocol ip parent 1:0 prio {$params->ctid} u32 match ip dst {$params->ip} flowid 1:{$params->bwin}";
                    $fo = "/sbin/tc filter add dev {$params->interface} protocol ip parent 1:0 prio {$params->ctid} u32 match ip dst {$params->ip} flowid 1:{$params->bwout}";
                    break;
                case 'v6':
                    $fi = "/sbin/tc filter add dev venet0 protocol ipv6 parent 1:0 prio {$params->ctid} u32 match ipv6 dst {$params->ip} flowid 1:{$params->bwin}";
                    $fo = "/sbin/tc filter add dev {$params->interface} protocol ipv6 parent 1:0 prio {$params->ctid} u32 match ip6 dst {$params->ip} flowid 1:{$params->bwout}";
                    break;
            }
            $commands = "/sbin/tc qdisc add dev venet0 root handle 1: htb
                /sbin/tc class add dev venet0 parent 1: classid 1:{$params->bwin} htb rate {$params->bwin}mbit
                /sbin/tc qdisc add dev venet0 parent 1:{$params->bwin} handle {$params->bwin}: sfq perturb 10
                {$fi}
                /sbin/tc qdisc add dev {$params->interface} root handle 1: htb
                /sbin/tc class add dev {$params->interface} parent 1: classid 1:{$params->bwout} htb rate {$params->bwout}mbit
                /sbin/tc qdisc add dev {$params->interface} parent 1:{$params->bwout} handle {$params->bwout}: sfq perturb 10
                {$fo}";
        }
        return ssh2_exec($this->ssh, $commands);
    }

    public static function tc_destroy($params){
        $commands = "/sbin/tc filter del dev venet0 prio {$params->ctid}
                /sbin/tc filter del dev {$params->interface} prio {$params->ctid}";
        return ssh2_exec($this->ssh, $commands);
    }

 

    /**
     * Helper : Check if VM's IP is v4 or v6 else return error
     * @param $ip string
     * @return string
     */
    public static function ip_check($ip){
        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
            $result = 'v4';
        } elseif(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
            $result = 'v6';
        } else {
            $result = 'error';
        }
        return $result;
    }


    /**
     * Get list of all VM's (Started, Stopped)
     * @return array
     */
    public static function vzlist(){
        $commands = "/usr/bin/sudo vzlist -j -a";
        $stream = ssh2_exec($this->ssh, $commands);

        stream_set_blocking($stream, true);
        $stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);

        $json = str_replace('9223372036854775807', '1', stream_get_contents($stream_out));
        $decoded_json = json_decode($json);

        return $decoded_json;
    }

    /**
     * Get VM's information by Container ID (ctid)
     * @param $ctid int
     * @return json array
     */
    public static function showVZ($ctid){
        $commands = "/usr/bin/sudo vzlist {$ctid} -j";
        $stream = ssh2_exec($this->ssh, $commands);

        stream_set_blocking($stream, true);
        $stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);

        $json = str_replace('9223372036854775807', '1', stream_get_contents($stream_out));
        $decoded_json = json_decode($json);

        return $decoded_json;
    }

    /**
     * ToDo : 
     * - Duplicate VMs
     */

}