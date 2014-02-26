# project-template

## DreamFactory Project Boilerplate v0.1.0

A basic structure for any DreamFactory repository project. Contains all the ingredients for a fine stew. All set up for **git-flow** as well.

In all the files there are placeholders for you to replace with your project particulars. In all but ```composer.json```,
the placeholders are surrounded by braces. In ```composer.json```, there are descriptive names so the schema validation passes. Just be aware.

# Installation

Clone this repository to your machine in the directory of your choosing.

In your ```/path/to/project/.git/config``` file is a line that specifies the URL to your actual repository:

	[remote.origin]
		url = git@bitbucket.org:dreamfactory/project-template.git

After the clone completes successfully, change this URL to point to where your project actually resides.
If you prefer, you may delete this line and use the ```git remote add``` command to add real repository to
your git configuration file.
