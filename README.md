# RD - SRAM Integration

This repository contains the [issue tracking](https://github.com/SURFnet/rd-sram-integration/milestones) and artifacts of the rd-sram-integration project.
All intellectual property in this project, including source code, ideas and documentation, is attributed to SURF.


### NB: The following functionality is still under construction

To see the basic testnet for milestone 1, take a linux machine with Docker installed, and run the following:
```
git clone https://github.com/SURFnet/rd-sram-integration
cd rd-sram-integration
git clone --branch=accept-ocm-to-groups --depth=1 http://github.com/pondersource/core
./scripts/gencerts.sh
./scripts/rebuild.sh
docker network create testnet
./scripts/start-testnet.sh
```
Now point your browser to port 5800 on that server, and in the browser-in-browser view, 
* open a tab, and log in to https://oc1.docker as einstein / relativity
* share a file with the group called 'federalists'
* open a tab, and log in to https://oc2.docker as marie / radioactivity
* you will see the file in question under 'Shared with you'

### Use case

We discussed three possible use cases:
* "addressbook" - allow the user to easily share with for instance the group of "people I invited for my birthday", but without making that list public. 
* "OCM groups" - as defined in the OCM spec, share to a group that is local at a remote site. For instance helpdesk@ (you know to which site your share goes, but not which users there are in that group)
* "SRAM groups" - the user sees both local custom groups and SRAM-hosted groups as possible sharees. When an SRAM group is selected, the share gets created locally, and one OCM invite gets sent to each remote site that hosts at least 1 group member. We use OCM share-to-group as the protocol, but it is understood that the remote site also looks up the member list for that group from SRAM.

The current plan is to only implement the third one ("SRAM" groups) and not the other two.
