## Process this file with automake to produce Makefile.in

SUBDIRS = etc lib portal kmtool sr \
	  ca openid bin sbin ch man doc

## In more modern versions of automake we can use
## AM_EXTRA_RECURSIVE_TARGETS in configure.ac to create a recursive
## cleandb target. This is a poor man's substitute that will suffice
## on older versions of automake.
cleandb:
	@$(MAKE) $(SUBDIRS) TARG=$@
$(SUBDIRS)::
	@cd $@; echo making $(TARG) in $@...; \
	$(MAKE) $(TARG)
.PHONY: $(SUBDIRS)
