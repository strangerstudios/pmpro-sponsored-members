#---------------------------
# This script generates a new pmpro.pot file for use in translations.
# To generate a new pmpro-sponsored-members.pot, cd to the main /pmpro-sponsored-members/ directory,
# then execute `languages/gettext.sh` from the command line.
# then fix the header info (helps to have the old pmpro.pot open before running script above)
# then execute `cp languages/pmpro-sponsored-members.pot languages/pmpro-sponsored-members.po` to copy the .pot to .po
# then execute `msgfmt languages/pmpro-sponsored-members.po --output-file languages/pmpro-sponsored-members.mo` to generate the .mo
#---------------------------
echo "Updating pmpro-sponsored-members.pot... "
xgettext -j -o languages/pmpro-sponsored-members.pot \
--default-domain=pmpro-sponsored-members \
--language=PHP \
--keyword=_ \
--keyword=__ \
--keyword=_e \
--keyword=_ex \
--keyword=_n \
--keyword=_x \
--sort-by-file \
--package-version=1.0 \
--msgid-bugs-address="info@paidmembershipspro.com" \
$(find . -name "*.php")
echo "Done!"