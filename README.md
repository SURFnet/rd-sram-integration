# RD - SRAM Integration

This repository contains the [issue tracking](https://github.com/SURFnet/rd-sram-integration/milestones) and artifacts of the rd-sram-integration project.
All intellectual property in this project, including source code, ideas and documentation, is attributed to SURF.


### NB: The following functionality is still under construction

To see the basic testnet for milestone 1, take a linux machine with Docker installed, and run the following:
```
git clone https://github.com/SURFnet/rd-sram-integration
cd rd-sram-integration
git clone --depth=1 http://github.com/owncloud/core
./scripts/gencerts.sh
./scripts/rebuild.sh
./scripts/start-testnet.sh
```
Now point your browser to port 5800 on that server, and in the browser-in-browser view, 
* open a tab, and log in to https://oc1.docker as einstein / relativity
* share a file with the group called 'federalists'
* open a tab, and log in to https://oc2.docker as marie / radioactivity
* you will see the file in question under 'Shared with you'