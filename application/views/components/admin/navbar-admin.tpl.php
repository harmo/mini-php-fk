    <nav class="navbar navbar-default navbar-fixed-top" role="navigation">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                    <span class="sr-only">Navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand logo" href="/<?php echo BASE_URL; ?>">Rust Life</a>
            </div>

            <div class="collapse navbar-collapse navbar-right" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    <li class="<?php echo $this->template == 'users' ? 'active' : ''; ?>">
                        <a href="/<?php echo BASE_URL; ?>admin/users">Membres</a>
                    </li>
                    <li class="<?php echo $this->template == 'grades' ? 'active' : ''; ?>">
                        <a href="/<?php echo BASE_URL; ?>admin/grades">Rangs</a>
                    </li>
                    <li class="<?php echo $this->template == 'permissions' ? 'active' : ''; ?>">
                        <a href="/<?php echo BASE_URL; ?>admin/permissions">Permissions</a>
                    </li>
                    <li class="<?php echo $this->template == 'clans' ? 'active' : ''; ?>">
                        <a href="/<?php echo BASE_URL; ?>admin/clans">Clans</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>