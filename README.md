# OpenVZApi for Symfony

This little bundle allow you to manage OpenVZ VMs from your Symfony application.

### 1. Installation

First import the bundle into your composer.json :

```json
[...]
    "require" : {
        [...]
        "FSALBER/OpenVZBundle" : "dev-master"
    },
[...]
    "repositories" : [{
        "type" : "vcs",
        "url" : { https://github.com/fsalber/OpenVZBundle.git }
    }],
[...]
```

After that, update your vendor : 

```sh
composer update
```

then, import it in your controller :

```php
use FSALBER\OpenVZBundle\OpenVZApi;
```

It's ready ! You can start using it !

### 2. Usage

The bundle is very simple to use. 
For example : We want to display every Containers (VMs) in our Index. We can use this code : 

```php
public function indexAction()
{
    // Call OpenVZApi class from OpenVZBundle with login info as param
    $api = new OpenVZApi('HOST', 'USERNAME', 'PASSWORD', PORT);

    // This command return you JSON Array as Stream
    $stream = $api->vzlist();

    // Let's decrypt Stream
    stream_set_blocking($stream, true);
    $stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);

    // Prevent PHP from display error due to too big integer
    $json = str_replace('9223372036854775807', '1', stream_get_contents($stream_out));

    // Decode JSON Array
    $decoded_json = json_decode($json);

    // Render your view and give as param the decoded json array
    return $this->render('YOURBUNDLE:YOURCONTROLLER:index.html.twig', array(
        'json' => $decoded_json
    ));
}
```

If you want to create a new one, we can use this example : 

```php
public function createAction()
{
    $params = new \stdClass();

    $params->ctid = 110;
    $params->template = "debian-8.0-x86_64";
    $params->disk = 10;
    $params->password = "test";
    $params->dns = 'YOUR DNS';
    $params->cpu_units = 1000;
    $params->cpu_limit = 2000;
    $params->hostname = 'YOUR HOSTNAME';
    $params->ram = 10;
    $params->burst = 100;
    $params->swap = 100;
    $params->cpus = 1;

    $api->create($params);

    // Be sure that you can use your own logic for create the params array.

    return $this->render('YOURBUNDLE:YOURCONTROLLER:index.html.twig');
}
```

### 3. Support

If you have any problems using this class, feel free to open a issues on github. We will take a look and try to find a solution. 