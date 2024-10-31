<div class="wrap pp-beta-test"><?php screen_icon(); ?>
	
	<h2>ProPhoto Beta Testing</h2>
	
	<h3>Environment Compatibility Report:</h3>
	
	<?php if ( floatval( phpversion() ) < 5.2 ) { ?>
		
		<div class="problem error">
			<p>
				<b>Sorry</b>, but in order to beta test ProPhoto, your server must be running <b>PHP 5.2.0</b> or higher.  
				You are currently running version <b><?php echo phpversion() ?></b>.
				You will need to contact your webhost technical support and ask them to upgrade your
				PHP configuration to PHP 5.2 or higher. You will have to do this very soon anyway, since as of 
				WordPress version 3.2, expected to be released mid-2011, WordPress itelf (not just ProPhoto) 
				will require PHP 5.2 or higher.
			</p>
		</div>
		
	<?php  } else { ?>
		
		<div class="check">
			<p>
				<b>Yay!</b> Your PHP version is high enough to run the beta version of ProPhoto.
			</p>
		</div>
		
	<?php } ?>
	
		
	<?php if ( $this->wpVersion() < 310 ) { ?>
		
		<div class="problem error">
			<p>
				<b>Sorry</b>, but in order to beta test ProPhoto, you must be running <b>WordPress version 3.1 or higher</b>.
				You're currently running version <?php echo $GLOBALS['wp_version']; ?>. 
				
				<?php if ( get_filesystem_method( array(), ABSPATH ) == 'direct' ) { ?>
					
					<a href="update-core.php">Click here</a> to automatically upgrade to WordPress 3.1+ so you can begin beta testing.
				
				<?php }  else { ?>
					
					Try upgrading automatically with the WordPress one-click upgrader by <a href="update-core.php">clicking here</a>,
					or else see our	<a href="http://www.prophotoblogs.com/support/about/wordpress-manual-upgrade/">tutorial on 
					upgrading WordPress</a> for more help.
					
				<?php } ?>
			</p>
		</div>
		
	<?php  } else { ?>
		
		<div class="check">
			<p>
				<b>Yay!</b> Your WordPress version is high enough to run the beta version of ProPhoto.
			</p>
		</div>
		
	<?php }?>	
		
		
		
	<?php if ( floatval( phpversion() ) < 5.2 || $this->wpVersion() < 310 ) { ?>
		
		<p>
			<b>FAIL</b> -- sorry, you must fix any issues described above before you can beta test ProPhoto.
		</p>
		
	<?php } else { ?>
		
		<p>
			<b>SUCCESS</b> -- your hosting and WordPress environment is totally compatible with the beta version of ProPhoto.
		</p>
		
	<?php } ?>	
		
	
</div>