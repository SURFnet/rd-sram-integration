# RD - SRAM Integration

This repository contains the [issue tracking](https://github.com/SURFnet/rd-sram-integration/milestones) and artifacts of the rd-SRAM-integration project.
All intellectual property in this project, including source code, ideas, and documentation, is attributed to SURF.

# Summary
The FederatedGroup application is the integration driver app that enables the ownCloud to connect to the SRAM and find out about federated group membership info using [SCIM Controller](https://github.com/SURFnet/rd-sram-integration/blob/mix-provider/ScimControllerDocs.md). Also, this application enables the user to share a file with a federated group across the network of multiple Owncloud nodes.

## For example:
Einstein on `OC1.Docker` can share a file with the `federalists` group and the resulting shared file will be accessible by all users stored in oc1.docker, oc2.docker, oc3.docker,... and are registered as members of the `federalists` group on SRAM.


# Installation

**Notes:**

1- At the first step you should install and enable the OpenCloudMesh App on your OwnCloud instance to enable the remote sharing. please check this repository to find more details:
> https://github.com/pondersource/oc-opencloudmesh  

2- You can find more detail about the Owncloud administration [here](https://doc.owncloud.com/server/next/admin_manual/configuration/server/occ_command.html);

Copy `federatedgroups` folder into the apps folder of your ownCloud. 
Change **sharing.managerFactory** config entry to **OCA\\FederatedGroups\\ShareProviderFactory** inside *config.php* file.

```
  'sharing.managerFactory' => 'OCA\\FederatedGroups\\ShareProviderFactory',
  'sharing.remoteShareesSearch' => 'OCA\\OpenCloudMesh\\ShareeSearchPlugin',
  'sharing.ocmController' => 'OCA\\OpenCloudMesh\\Controller\\OcmController',
  'sharing.groupExternalManager' => 'OCA\\OpenCloudMesh\\GroupExternalManager',
```


## configuration
there are two configurations that should be done in the oc_appconfig table: 

```
+-----------------+-------------+--------------------------------------------------+
| appid           | configkey   | configvalue                                      |
+-----------------+-------------+--------------------------------------------------+
| federatedgroups | allowed_ips | some comma seperated valid ips or (*)            |
| federatedgroups | scim_token  | some top secret random generated secrets         |
+-----------------+-------------+--------------------------------------------------+
```

this configuration will be done with the first SCIM Api call but you can set them by these SQL commands:
> insert into oc_appconfig (appid, configkey, configvalue) VALUES ('federatedgroups', 'scim_token', 'something-super-secret');

> insert into oc_appconfig (appid, configkey, configvalue) VALUES ('federatedgroups', 'allowed_ips', '<* | desiered white listed ips (seperated by comma)>');

AND HERE WE GO!!!!! 🚀


## Testing environment:

you can test this application using this repository: 
https://github.com/pondersource/dev-stock

### Note:
You should install Docker on your system as a requirement **OR** just using the **Github Codespaces**

### Known issue

Step 3 will throw an error but we think it can be safely ignored, see https://github.com/SURFnet/rd-sram-integration/issues/235.

### Instruction:

1- Clone the [pondersource dev-stock repository](https://github.com/pondersource/dev-stock).

2- Run `./scripts/init-rd-sram.sh`.

3- Run `./scripts/testing-rd-sram.sh`.

4- Run `docker exec -it oc2.docker sh /curls/includeMarie.sh oc2.docker`

5- Run `docker exec -it oc1.docker sh /curls/includeMarie.sh oc1.docker`

After Running these commands you can browse localhost:5800 and see a headless browser.
inside the headless browser, you can enter these two addresses: https://oc1.docker and https://oc2.docker.

login into these instances of OwnCloud with these credentials: 

oc1.docker: 
  > username: einstein 
  > 
  > password: relativity
  
oc2.docker:
  > username: marie
  > 
  > password: radioactivity
  
and finally, you can share a file with the `federalists` Group for example on oc1.docker.

then you can browse the oc2.docker and see the incoming share dialog.

If you can prefer you can use [Reza's bootstrap script](https://github.com/pondersource/dev-stock/blob/main/bootstrap-rd-sram.sh) that executes steps 1-5.
