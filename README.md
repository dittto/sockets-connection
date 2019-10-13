# Sockets - connection layer

This is a PHP 7.2 application that uses [Ratchet](http://socketo.me) to create WebSockets endpoint. 

It's mainly for explaining how WebSockets can work with both JS and Unity, so it has a simple design for registering a connection as either a *Server* or a *Client* and passing information between them. 

This repository comes with it's own Dockerfile for both development and production. To run this locally for development, simply run the following commands:

```bash
docker build -t sockets-connection:latest .
docker-compose up -d --build
docker exec -it sockets-connection bash
composer install
php bin/server.php
```

The above uses `docker-compose` to build and run a Docker image, which mounts the current directory as a volume inside of the container. This is so it's easy to work on your code and simply restart the server to test changes. When `server.php` is running, it will output a stream of who's connecting and sending messages. To quit, press `Ctrl-C`.

It uses both a `docker build` command, and a `docker-compose` command as the first builds the `Dockerfile` into a Docker image, and the second uses that image and then overrides some options for development. These overridden options occur in both the `Dockerfile-dev` and the `docker-compose.yaml`.  

If you want to run it locally, pretending to be for production, then run the following commands:

```bash
docker build -t sockets-connection:latest .
docker run -d --name sockets-connection --restart unless-stopped -p 100:8080 sockets-connection:latest
docker logs -f sockets-connection
```

This code will build and run a production-ready Docker image. The production version copies all files into it's container, so if you update any files locally without re-building the Docker image, you won't see any changes.

The last line outputs the stream of connection information from `server.php` mentioned above. This is so you can debug it if something's going weird, or simply data-mine it to find out what's happening when and where.

## How to use it

For our examples of how to use this code, we're going to use JavaScript as it's easy enough to open Chrome, open the console, and copy / paste these in to test everything's working. 

The first part is connecting. When any user connects, the `connection layer` stores their existence. At this point in a connection lifecycle, we don't know if they're a *client* or a *server*. 

```js
var conn = new WebSocket('ws://localhost:100');
conn.onopen = function (e) {
    console.log("Connection established!");
};
conn.onmessage = function (e) {
    console.log(e.data);
};
conn.onerror = function (e) {
    console.log("Error: " + e.data);    
};
```

Assuming the response from above is `Connection established!`, we can start define what type of user we are. We do this with the `connect` command. Once we've sent a `connect` command, we can start sending over data. 

Data is stored for each *client* and *server* and sent using the `data` command. It merges with any existing data already stored for that given user. All communication with the `connection layer` is done using *JSON*. 

The following shows a client connecting and then sending over information about itself - it's `name`, `x` position, and `y` position:

```js
conn.send('{"connect": "client"}');
conn.send('{"data": {"name": "Bob", "x": 12, "y": 16}}'); 
```

When the `connection layer` receives the `connect` command, it defines the connection as a *client*, sends the individual client's data to the server, and the server's data back to the client.

When it then receives the `data` command, it sends the individual client's newly-merged data to the server, if the server's been set.

The next block shows us connecting a server. To test this locally, have two consoles windows open and run the connection code in each one. Then set one as a client and one as server.

```js
conn.send('{"connect": "server"}');
conn.send('{"data": {"map_id": "1"}}');
conn.send('{"data": {"clients": [{"id": 11, "x": 0.4, "y": 0.3}]}}');
```

As above, when the `connection layer` receives the `connect` command, it defines the connection as a *server*. It then sends the server data to every connected client, and every client's data back to the server.

There are two `data` commands shown above. The first will be merged with the `connection layer's` server data and then sent to every client. The second command will simply be sent to that single `client_id`. The reason for this is to allow the server to send out broadcast messages, and to also send secret messages to individual clients. If you wanted all clients to know where all others were, you would include that in the general server data.

When we're done with the connection, we simply close it by running the following:

```js
conn.close();
```

Closing the connection as *client* will inform the server that the client has disconnected. If you close the connection as a *server*, then all clients will be told.

One important note is this test is set up to run over http only, so Chrome will error if you're trying to access it from an https website. 

## How to update Docker repository

To make your code easy to update, we're going to cover how to store your docker image in AWS' ECR. First, you'll need to have an AWS account and have created a new repository in ECR.

First, tag your repository with a version number:

```bash
git tag -a <tag>
git tag
git push origin <tag>
```

Then, tag and push image to ECR:

```bash
$(aws ecr get-login --no-include-email --region eu-west-1 --profile default)
docker build -t sockets-connection:latest .
docker tag sockets-connection:latest <ecr_repo_url>/sockets-connection:<tag>
docker push <ecr_repo_url>/sockets-connection:<tag>
```

## Setup AWS server

To run this code, we're going to keep it simple and use AWS EC2. A better choice would be AWS Fargate as it handles the Docker side of things itself, but EC2's simple and it's useful to know the basics.

For this example, start an EC2 instance with `Ubuntu Server 16.04 LTS (HVM) - ami-58d7e821`. This default login for this server is `ubuntu`.

Keep going through the pages of the setup wizard until you reach the `Security group` page.

We're going to want to add an extra a new `TCP` rule for port `8080`, from any source. This is to allow the outside world to access our `connection layer`.

Once this is done, launch the EC2 instance and wait for it to start. When it's running, log in using the `*.pem` file it let you download and run the following commands:

```bash
# login using pem
ssh -i "ec2.pem" ubuntu@<ec2-public-ip-address>

# update packages
sudo apt-get update

# install aws cli and the enter some aws credentials with enough access to read AWS ECS
sudo apt-get install awscli
aws configure

# install docker
sudo apt-get install docker.io
sudo systemctl status docker
sudo usermod -aG docker ${USER}

# exit so the usermod changes take effect
exit
```

Log back into the server, as before, and run the following to set up the server:

```bash
$(aws ecr get-login --region eu-west-1 --profile default)
docker pull <ecr_repo_url>/sockets-connection:<tag>
docker run -d --name sockets --restart unless-stopped -p 8080:8080 <ecr_repo_url>/sockets-connection:<tag>
docker logs -f sockets
```

If you now run the above JavaScript examples, remembering to substitute the `localhost` url for the ip address of your new server, you should be able to connect and see the result in the logs listed above. 
