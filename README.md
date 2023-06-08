# RD - SRAM Integration

This repository contains the [issue tracking](https://github.com/SURFnet/rd-sram-integration/milestones) and artifacts of the rd-sram-integration project.
All intellectual property in this project, including source code, ideas and documentation, is attributed to SURF.

# Summary
The FederatedGroup application is the integration deriver app which enable the own cloud to connect to the SRAM and find out about fedrated group membership info using [SCIM Controller](https://github.com/SURFnet/rd-sram-integration/blob/mix-provider/ScimControllerDocs.md). Also, this application enables the user to share a file with a federated group across the network of multiple Owncloud nodes.

## For example:
Einstein on `OC1.Docker` can share a file with the `federalists` group and the resulting shared file will be accessible by all users they stored in OC1.Docker, OC2.Docker, OC3.Docker,... and are registered as members of the `federalists` group on SRAM.


# Installation

**Notes:**

1- At the first step you should install and enable the OpenCloudMesh App on your OwnCloud instance to enable the remote sharing on it. please chack this repository to find more details:
> https://github.com/pondersource/oc-opencloudmesh  

2- You can find more detail about the Owncloud administration [here](https://doc.owncloud.com/server/next/admin_manual/configuration/server/occ_command.html);

Copy `federatedgroups` folder into apps folder of your owncloud. 
Change **sharing.managerFactory** config entry to **OCA\\FederatedGroups\\ShareProviderFactory** inside *config.php* file.

## configuration
there are two configuration those should be done in oc_appconfig table: 

```
+-----------------+-------------+--------------------------------------------------+
| appid           | configkey   | configvalue                                      |
+-----------------+-------------+--------------------------------------------------+
| federatedgroups | allowed_ips | some comma seperated valid ips or (*)            |
| federatedgroups | scim_token  | some top secret random generated secrets         |
+-----------------+-------------+--------------------------------------------------+
```

these configuration will done with the first SCIM Api call but you can set them by these sql commands:
> insert into oc_appconfig (appid, configkey, configvalue) VALUES ('federatedgroups', 'scim_token', 'something-super-secret');

> insert into oc_appconfig (appid, configkey, configvalue) VALUES ('federatedgroups', 'allowed_ips', '<* | desiered white listed ips (seperated by comma)>');

AND HERE WE GO!!!!! 🚀


## Testing environment:

you can test this application using using this repository: 
https://github.com/pondersource/dev-stock

### Note:
You should install Docker on you system as requirement **OR** just using the **Github Codespaces**

### Instruction:

1- Clone the repository.

2- Change the branch to `oc-opencloudmesh-testing`.

3- Run `./scripts/clean.sh`.

4- Run `./scripts/init-rd-sram.sh`.

5- Run `./scripts/rd-sram-testing.sh`.

After Running these commands you can browse localhost:5800 and it let you see a headless browser.
inside headless browser you can enter these two addresses: https://oc1.docker and https://oc2.docker.

login into these instances of OwnCloud by these credentials: 

oc1.docker: 
  > username: einstein 
  > 
  > password: relativity
  
oc2.docker:
  > username: marie
  > 
  > password: radioactivity
  
and finally you can share a file with the `federalists` Group for example on oc1.docker.

then you can browse the oc2.docker and see the incomming share dialog.

