<?php
include_once('../../../config/config.inc.php');
include_once('../../../init.php');


$context = Context::getContext();
$cookie = new Cookie('psAdmin', '', (int) Configuration::get('PS_COOKIE_LIFETIME_BO'));
$employee = new Employee((int) $cookie->id_employee);

if (!(Validate::isLoadedObject($employee) && $employee->checkPassword((int) $cookie->id_employee, $cookie->passwd) && (!isset($cookie->remote_addr) || $cookie->remote_addr == ip2long(Tools::getRemoteAddr()) || !Configuration::get('PS_COOKIE_CHECKIP')))) {
    die('User is not logged in');
}
/**
 * Copyright 2010 Cyrille Mahieux
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and limitations
 * under the License.
 *
 * ><)))°> ><)))°> ><)))°> ><)))°> ><)))°> ><)))°> ><)))°> ><)))°> ><)))°>
 *
 * Stats viewing
 *
 * @author c.mahieux@of2m.fr
 * @since  20/03/2010
 */
# Headers
header('Content-type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

# Require
require_once 'Library/Loader.php';
require_once 'Library/Configuration/Loader.php';
require_once 'Library/HTML/Components.php';
require_once 'Library/Command/Factory.php';
require_once 'Library/Command/Interface.php';
require_once 'Library/Command/Memcache.php';
require_once 'Library/Command/Memcached.php';
require_once 'Library/Command/Server.php';
require_once 'Library/Data/Analysis.php';
require_once 'Library/Data/Error.php';
require_once 'Library/Data/Version.php';


# Date timezone
date_default_timezone_set('Europe/Paris');

# Loading ini file
$_ini = Library_Configuration_Loader::singleton();

# Initializing requests
$request = (isset($_GET['show'])) ? $_GET['show'] : null;

# Getting default cluster
if (!isset($_GET['server'])) {
    $clusters = array_keys($_ini->get('servers'));
    $cluster = isset($clusters[0]) ? $clusters[0] : null;
    $_GET['server'] = $cluster;
}

# Showing header
include 'View/Header.tpl';

# Display by request type
switch ($request) {
    # Items : Display of all items for a single slab for a single server
    case 'items':
        # Initializing items array
        $server = null;
        $items = false;
        $response = [];

        # Ask for one server and one slabs items
        if (isset($_GET['server']) && ($server = $_ini->server($_GET['server']))) {
            $items = Library_Command_Factory::instance('items_api')->items($server['hostname'], $server['port'], $_GET['slab']);
        }

        # Cheking if asking an item
        if (isset($_GET['request_key'])) {
            $response[$server_name] = Library_HTML_Components::serverResponse($server['hostname'], $server['port'],
                Library_Command_Factory::instance('get_api')->get($server['hostname'], $server['port'], $_GET['request_key']));
        }

        # Getting stats to calculate server boot time
        $stats = Library_Command_Factory::instance('stats_api')->stats($server['hostname'], $server['port']);
        $infinite = (isset($stats['time'], $stats['uptime'])) ? ($stats['time'] - $stats['uptime']) : 0;

        # Items are well formed
        if ($items !== false) {
            # Showing items
            include 'View/Stats/Items.tpl';
        } # Items are not well formed
        else {
            include 'View/Stats/Error.tpl';
        }
        unset($items);
        break;

    # Slabs : Display of all slabs for a single server
    case 'slabs':
        # Initializing slabs array
        $slabs = false;

        # Ask for one server slabs
        if (isset($_GET['server']) && ($server = $_ini->server($_GET['server']))) {
            # Spliting server in hostname:port
            $slabs = Library_Command_Factory::instance('slabs_api')->slabs($server['hostname'], $server['port']);
        }

        # Slabs are well formed
        if ($slabs !== false) {
            # Analysis
            $slabs = Library_Data_Analysis::slabs($slabs);
            include 'View/Stats/Slabs.tpl';
        } # Slabs are not well formed
        else {
            include 'View/Stats/Error.tpl';
        }
        unset($slabs);
        break;

    # Default : Stats for all or specific single server
    default :
        # Initializing stats & settings array
        $stats = [];
        $slabs = [];
        $slabs['total_malloced'] = 0;
        $slabs['total_wasted'] = 0;
        $settings = [];
        $status = [];

        $cluster = null;
        $server = null;

        # Ask for a particular cluster stats
        if (isset($_GET['server']) && ($cluster = $_ini->cluster($_GET['server']))) {
            foreach ($cluster as $server_name => $server) {
                # Getting Stats & Slabs stats
                $data = [];
                $data['stats'] = Library_Command_Factory::instance('stats_api')->stats($server['hostname'], $server['port']);
                $data['slabs'] = Library_Data_Analysis::slabs(Library_Command_Factory::instance('slabs_api')->slabs($server['hostname'], $server['port']));
                $stats = Library_Data_Analysis::merge($stats, $data['stats']);

                # Computing stats
                if (isset($data['slabs']['total_malloced'], $data['slabs']['total_wasted'])) {
                    $slabs['total_malloced'] += $data['slabs']['total_malloced'];
                    $slabs['total_wasted'] += $data['slabs']['total_wasted'];
                }
                $status[$server_name] = ($data['stats'] != []) ? $data['stats']['version'] : '';
                $uptime[$server_name] = ($data['stats'] != []) ? $data['stats']['uptime'] : '';
            }
        } # Asking for a server stats
        elseif (isset($_GET['server']) && ($server = $_ini->server($_GET['server']))) {
            # Getting Stats & Slabs stats
            $stats = Library_Command_Factory::instance('stats_api')->stats($server['hostname'], $server['port']);
            $slabs = Library_Data_Analysis::slabs(Library_Command_Factory::instance('slabs_api')->slabs($server['hostname'], $server['port']));
            $settings = Library_Command_Factory::instance('stats_api')->settings($server['hostname'], $server['port']);
        }

        # Stats are well formed
        if (($stats !== false) && ($stats != [])) {
            # Analysis
            $stats = Library_Data_Analysis::stats($stats);
            include 'View/Stats/Stats.tpl';
        } # Stats are not well formed
        else {
            include 'View/Stats/Error.tpl';
        }
        unset($stats);
        break;
}
# Showing footer
include 'View/Footer.tpl';
